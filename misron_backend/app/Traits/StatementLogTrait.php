<?php

namespace App\Traits;

use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

trait StatementLogTrait
{
    /**
     * Insert statement log - Uses ACTUAL account balance from chart_of_accounts
     * Uses row-level locking to prevent race conditions
     */
    protected function insertStatementLog(
        $accountId,
        $date,
        $referenceNo,
        $type,
        $debit,
        $credit,
        $desc,
        $sourceModel = null
    ) {
        // Use a fresh query with lock to get the REAL current balance
        $account = ChartOfAccount::where('id', $accountId)
            ->lockForUpdate()
            ->first();
        
        if (!$account) {
            throw new \Exception('Account not found: ' . $accountId);
        }

        // Get the ACTUAL balance from the locked row
        $newBalance = $account->current_balance;

        \Log::info('Statement log balance', [
            'account_id' => $accountId,
            'account_name' => $account->name,
            'balance_used' => $newBalance,
            'debit' => $debit,
            'credit' => $credit,
            'reference_no' => $referenceNo
        ]);

        $data = [
            'account_id'       => $accountId,
            'transaction_date' => $date,
            'reference_no'     => $referenceNo,
            'transaction_type' => $type,
            'debit'            => $debit,
            'credit'           => $credit,
            'balance'          => $newBalance,
            'description'      => $desc,
            'source_id'        => $sourceModel ? $sourceModel->id : null,
            'source_type'      => $sourceModel ? get_class($sourceModel) : null,
        ];

        // Add optional foreign keys if they exist on the source model
        if ($sourceModel) {
            if ($sourceModel instanceof \App\Models\Voucher) {
                $data['voucher_id'] = $sourceModel->id;
            }
            if (isset($sourceModel->voucher_id)) {
                $data['voucher_id'] = $sourceModel->voucher_id;
            }
            if (isset($sourceModel->order_id)) {
                $data['order_id'] = $sourceModel->order_id;
            }
        }

        return AccountStatementLog::create($data);
    }
}