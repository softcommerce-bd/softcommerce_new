<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;

/** Looks up the accounts the new purchasing flow posts to; creates the clearing accounts that did not exist before. */
class SystemAccounts
{
    private const DEFS = [
        'inventory' => ['Inventory', 'asset', 'inventory', 'SYS-INV'],
        'payable' => ['Accounts Payable', 'liability', 'payable', 'SYS-AP'],
        'grni' => ['Goods Received Not Invoiced', 'liability', 'payable', 'SYS-GRNI'],
        'variance' => ['Purchase Price Variance', 'expense', 'expense', 'SYS-PPV'],
        'advance' => ['Supplier Advances', 'asset', 'receivable', 'SYS-ADV'],
    ];

    /** The accounts the migration creates (so first use never has to). */
    public static function definitions(): array
    {
        return self::DEFS;
    }

    public static function id(string $key): int
    {
        [$name, $type, $sub, $code] = self::DEFS[$key];
        $find = fn () => ChartOfAccount::where('name', $name)->where('type', $type)->where('is_deleted', '0')->first() ?? ChartOfAccount::where('code', $code)->first();
        $acc = $find();
        if (!$acc) {
            try {
                $acc = ChartOfAccount::create(['code' => $code, 'name' => $name, 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0, 'description' => 'Created automatically by the purchasing upgrade.']);
            } catch (\Illuminate\Database\QueryException $e) {
                $acc = $find() ?? throw $e; // another request created it first
            }
        }

        return (int) $acc->id;
    }
}
