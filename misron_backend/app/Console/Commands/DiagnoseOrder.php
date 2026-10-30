<?php

namespace App\Console\Commands;

use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use App\Models\CourierSettlement;
use App\Models\Order;
use Illuminate\Console\Command;

class DiagnoseOrder extends Command
{
    protected $signature = 'debug:order {order_id}';

    protected $description = 'Diagnose all records and accounting for an order';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        $order = Order::with('orderDetails')->find($orderId);

        if (!$order) {
            $this->error("Order #{$orderId} not found.");
            return 1;
        }

        $this->line("=== ORDER ===");
        $this->line("ID:          {$order->id}");
        $this->line("Code:        {$order->code}");
        $this->line("Order from:  {$order->order_from}");
        $this->line("Grand total: {$order->grand_total}");
        $this->line("Due amount:  {$order->due_amount}");
        $this->line("Courier st:  {$order->courier_status}");
        $this->line("Payment st:  {$order->payment_status}");
        $this->line("Settle st:   {$order->settlement_status}");

        $this->line("\n=== ORDER DETAILS ===");
        foreach ($order->orderDetails as $d) {
            $this->line("  #{$d->id} product={$d->product_id} qty={$d->quantity} price={$d->price} shipping={$d->shipping_cost} deliv_status={$d->delivery_status}");
        }

        $settlement = CourierSettlement::where('order_id', $orderId)->first();
        if ($settlement) {
            $this->line("\n=== COURIER SETTLEMENT ===");
            $this->line("ID:            {$settlement->id}");
            $this->line("Settlement no: {$settlement->settlement_no}");
            $this->line("Total amount:  {$settlement->total_amount}");
            $this->line("Courier fee:   {$settlement->courier_fee}");
            $this->line("Settlement fee:{$settlement->settlement_fee}");
            $this->line("Net payable:   {$settlement->net_payable}");
            $this->line("Status:        {$settlement->status}");

            $this->line("\n=== SETTLEMENT DETAILS ===");
            foreach ($settlement->settlementDetails as $sd) {
                $this->line("  detail#{$sd->id} product={$sd->product_name} qty={$sd->quantity} price={$sd->price} shipping={$sd->shipping_cost} delivery_status={$sd->delivery_status}");
            }
        } else {
            $this->line("\nNo settlement record.");
        }

        $this->line("\n=== ACCOUNT STATEMENT LOGS (order_id={$orderId}) ===");
        $logs = AccountStatementLog::where('order_id', $orderId)->orderBy('id')->get();
        if ($logs->isEmpty()) {
            $this->line("(none)");
        }
        foreach ($logs as $l) {
            $this->line("  #{$l->id} acc={$l->account_id} date={$l->transaction_date} ref={$l->reference_no} type={$l->transaction_type} debit={$l->debit} credit={$l->credit} desc={$l->description}");
        }

        // AR balance
        $arAccount = ChartOfAccount::getAccountsReceivable();
        $this->line("\n=== ACCOUNTS RECEIVABLE ===");
        $this->line("ID:      " . ($arAccount ? $arAccount->id : 'not found'));
        $this->line("Balance: " . ($arAccount ? $arAccount->current_balance : 'N/A'));
        $this->line("\nAll AR logs:");
        $arLogs = AccountStatementLog::where('account_id', $arAccount ? $arAccount->id : 0)
            ->orderBy('id')->get();
        foreach ($arLogs as $l) {
            $this->line("  #{$l->id} debit={$l->debit} credit={$l->credit} balance={$l->balance} ref={$l->reference_no} type={$l->transaction_type} desc={$l->description}");
        }

        return 0;
    }
}
