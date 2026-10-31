<?php

namespace App\Services\Accounting;

use App\Services\Inventory\Idempotency;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Accounting for money (or credit) given back to a customer. The sale is reversed the same way whatever the channel;
 * only the credit side differs, because the money owed to the customer sits somewhere different:
 *
 *   wallet   Dr Sales Returns and Refunds   Cr Customer Wallet Balances  (a liability: we now owe the customer wallet credit)
 *   cash     Dr Sales Returns and Refunds   Cr the cash / bank account that paid
 *   gateway  Dr Sales Returns and Refunds   Cr Payment Gateway Clearing  (the gateway returned the money; needs the gateway's refund reference)
 *
 * Nothing is posted twice: a refund request can be paid once (unique), a gateway reference can be used once (unique), the same
 * idempotency key replays the first result, and the total refunded for an order line can never exceed what the line was sold for.
 * Cost of goods is NOT touched: stock returns only through the return-receipt workflow, which restores the units and their cost layers.
 */
class RefundService
{
    public function __construct(private JournalService $journal)
    {
    }

    public function record(string $key, string $method, $amount, ?int $orderId = null, ?int $orderDetailId = null, ?int $refundRequestId = null, ?int $accountId = null, ?string $externalRef = null, ?int $by = null, ?string $note = null): array
    {
        return Idempotency::once('refund', $key, function () use ($key, $method, $amount, $orderId, $orderDetailId, $refundRequestId, $accountId, $externalRef, $by, $note) {
            if (!in_array($method, ['wallet', 'cash', 'gateway'], true)) {
                throw new InvalidArgumentException('Refund method must be wallet, cash or gateway.');
            }
            $cents = Money::cents(Money::micro($amount));
            if ($cents <= 0) {
                throw new InvalidArgumentException('The refund amount must be positive.');
            }
            if ($method === 'cash' && !$accountId) {
                throw new InvalidArgumentException('A cash refund needs the cash or bank account it was paid from.');
            }
            if ($method === 'gateway' && trim((string) $externalRef) === '') {
                throw new InvalidArgumentException("A gateway refund needs the gateway's refund reference.");
            }
            // Lock order: the order line first, so every refund of one line is serialised (no gap-lock deadlocks between them);
            // the unique keys are the final authority on duplicates, the checks below only give a friendly message.
            $line = null;
            if ($orderDetailId) {
                $line = DB::table('order_details')->where('id', $orderDetailId)->lockForUpdate()->first() ?: throw new InvalidArgumentException('Unknown order line.');
            }
            if ($refundRequestId && DB::table('refund_postings')->where('refund_request_id', $refundRequestId)->exists()) {
                throw new InvalidArgumentException('This refund request has already been paid.');
            }
            if ($externalRef && DB::table('refund_postings')->where('external_ref', $externalRef)->exists()) {
                throw new InvalidArgumentException('That gateway refund reference was already recorded.');
            }
            if ($line) {
                $limit = Money::cents(Money::micro($line->price) + Money::micro($line->tax ?? 0));
                $done = Money::cents(Money::micro(DB::table('refund_postings')->where('order_detail_id', $orderDetailId)->sum('amount')));
                if ($done + $cents > $limit) {
                    throw new InvalidArgumentException('Refunds for this order line would exceed what it was sold for (already refunded ' . number_format($done / 100, 2, '.', '') . ').');
                }
            }
            $credit = match ($method) {
                'wallet' => SystemAccounts::id('customer_wallet'),
                'cash' => $accountId,
                'gateway' => SystemAccounts::id('gateway_clearing'),
            };
            $memo = ucfirst($method) . ' refund' . ($orderId ? " (order {$orderId})" : '') . ($externalRef ? " ref {$externalRef}" : '');
            $j = $this->journal->post('refund:' . $key, now()->toDateString(), [
                ['account' => SystemAccounts::id('sales_returns'), 'debit' => $cents, 'memo' => $memo],
                ['account' => $credit, 'credit' => $cents, 'memo' => $memo],
            ], 'refund', $refundRequestId ?? $orderDetailId, $memo, $by);
            try {
                $id = DB::table('refund_postings')->insertGetId(['refund_request_id' => $refundRequestId, 'order_id' => $orderId, 'order_detail_id' => $orderDetailId, 'method' => $method, 'amount' => $cents / 100,
                'account_id' => $method === 'cash' ? $accountId : null, 'external_ref' => $externalRef, 'idempotency_key' => $key, 'journal_entry_id' => $j['journal_entry_id'], 'created_by' => $by, 'note' => $note]);
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) { // two requests raced past the checks: the unique key decides, and the journal entry above rolls back with this transaction
                    throw new InvalidArgumentException('This refund (request or gateway reference) was already recorded.');
                }
                throw $e;
            }

            return ['refund_posting_id' => $id, 'journal_entry_id' => $j['journal_entry_id'], 'amount' => $cents / 100];
        });
    }
}
