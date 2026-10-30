<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\AccountStatementLog;
use App\Models\Voucher;
use App\Models\VoucherDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChartOfAccountController extends Controller
{
    /**
     * Get account details for editing
     */
    public function edit($id)
    {
        try {
            $account = ChartOfAccount::findOrFail($id);

            Log::info('Editing account:', [
                'id' => $account->id,
                'name' => $account->name,
                'parent_id' => $account->parent_id,
                'sub_type' => $account->sub_type
            ]);

            return response()->json([
                'success' => true,
                'data' => $account
            ]);
        } catch (\Exception $e) {
            Log::error('Error editing account: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Account not found'
            ], 404);
        }
    }

    /**
     * Store a new account
     */

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'sub_type' => 'nullable|string',
            'opening_balance' => 'nullable|numeric',
            'cashout_charge' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Auto generate account code
            $code = $this->generateAccountCode($request->sub_type, $request->type);

            // Handle parent_id
            $parentId = null;
            if ($request->has('parent_id') && !empty($request->parent_id)) {
                $parentId = $request->parent_id;
            }

            // DEBUG: Check what data is being passed
            \Log::info('Creating account with data:', [
                'code' => $code,
                'name' => $request->name,
                'type' => $request->type,
                'sub_type' => $request->sub_type,
                'parent_id' => $parentId,
                'opening_balance' => $request->opening_balance ?? 0,
                'cashout_charge' => $request->cashout_charge ?? 0,
            ]);

            // Create account
            $account = ChartOfAccount::create([
                'code' => $code,
                'name' => $request->name,
                'type' => $request->type,
                'sub_type' => $request->sub_type,  // Make sure this is being set
                'parent_id' => $parentId,
                'opening_balance' => $request->opening_balance ?? 0,
                'current_balance' => $request->opening_balance ?? 0,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'mobile_number' => $request->mobile_number,
                'status' => $request->status ?? 'active',
                'description' => $request->description,
                'cashout_charge' => $request->cashout_charge ?? 0,
            ]);

            // DEBUG: Check what was saved
            \Log::info('Account created:', $account->toArray());

            // Create opening balance statement log if opening balance > 0
            if ($request->opening_balance > 0) {
                $this->createOpeningBalanceLog($account, $request->as_of_date ?? now());
            }

            // Update Owners Equity account for opening balance (double-entry)
            if ($request->opening_balance > 0 && $account->sub_type !== 'equity') {
                $this->updateOwnersEquityForOpening($account, $request->as_of_date ?? now());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $account
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Store account error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an account
     */
    /**
     * Update an account - NO voucher, just update statement log
     */
    public function update(Request $request, $id)
    {
        $account = ChartOfAccount::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'opening_balance' => 'nullable|numeric',
            'as_of_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Prepare update data
            $updateData = [
                'name' => $request->name,
                'type' => $request->type,
                'sub_type' => $request->sub_type,
                'bank_name' => $request->bank_name,
                'account_number' => $request->account_number,
                'mobile_number' => $request->mobile_number,
                'status' => $request->status ?? 'active',
                'description' => $request->description,
                'cashout_charge' => $request->cashout_charge ?? 0,
            ];

            // Handle parent_id
            $updateData['parent_id'] = ($request->has('parent_id') && !empty($request->parent_id))
                ? $request->parent_id
                : null;

            // Check if opening_balance has changed
            if ($request->has('opening_balance') && $request->opening_balance != $account->opening_balance) {
                $asOfDate = $request->has('as_of_date') ? $request->as_of_date : now();

                // Just update the balance and create a statement log - NO VOUCHER
                $oldBalance = $account->opening_balance;
                $difference = $request->opening_balance - $oldBalance;

                // Update balances
                $updateData['opening_balance'] = $request->opening_balance;
                $updateData['current_balance'] = $account->current_balance + $difference;

                // Create adjustment log ONLY (No voucher)
                // For asset/expense: increase = debit, decrease = credit
                // For liability/equity/income: increase = credit, decrease = debit
                $isDebitType = in_array($account->type, ['asset', 'expense']);
                $adjDebit = 0;
                $adjCredit = 0;
                if ($difference > 0) {
                    if ($isDebitType) {
                        $adjDebit = abs($difference);
                    } else {
                        $adjCredit = abs($difference);
                    }
                } else {
                    if ($isDebitType) {
                        $adjCredit = abs($difference);
                    } else {
                        $adjDebit = abs($difference);
                    }
                }

                AccountStatementLog::create([
                    'account_id' => $account->id,
                    'transaction_date' => $asOfDate,
                    'reference_no' => 'ADJUSTMENT',
                    'transaction_type' => 'opening_balance_adjustment',
                    'debit' => $adjDebit,
                    'credit' => $adjCredit,
                    'balance' => $account->current_balance + $difference,
                    'description' => "Opening balance adjusted from {$oldBalance} to {$request->opening_balance}",
                ]);

                // Also update Owners Equity for this adjustment
                if ($account->sub_type !== 'equity' && $difference != 0) {
                    $this->updateOwnersEquityForAdjustment($account, $difference, $asOfDate, $oldBalance, $request->opening_balance);
                }
            }

            $account->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'data' => $account
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update account error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an account
     */
    public function destroy($id)
    {
        $account = ChartOfAccount::findOrFail($id);

        // Check if account has children
        if ($account->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account with child accounts'
            ], 422);
        }

        // Check if account has balance
        if ($account->current_balance != 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account with non-zero balance'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Delete statement logs
            $account->statements()->delete();

            // Soft delete the account
            $account->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete account error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete account'
            ], 500);
        }
    }

    /**
     * Update account balance (manual adjustment)
     */
    public function updateBalance(Request $request, $id)
    {
        $account = ChartOfAccount::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'adjustment_amount' => 'required|numeric',
            'adjustment_type' => 'required|in:add,subtract',
            'description' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $amount = $request->adjustment_amount;
            $oldBalance = $account->current_balance;

            // Update balance
            if ($request->adjustment_type === 'add') {
                $account->current_balance += $amount;
            } else {
                $account->current_balance -= $amount;
            }
            $account->save();

            // Create adjustment log
            AccountStatementLog::create([
                'account_id' => $account->id,
                'transaction_date' => now(),
                'reference_no' => 'ADJ' . time(),
                'transaction_type' => 'journal_entry',
                'debit' => ($request->adjustment_type === 'add' && $account->type === 'asset') ? $amount : 0,
                'credit' => ($request->adjustment_type === 'subtract' && $account->type === 'asset') ? $amount : 0,
                'balance' => $account->current_balance,
                'description' => $request->description,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Balance adjusted successfully',
                'new_balance' => $account->current_balance,
                'old_balance' => $oldBalance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Balance adjustment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust balance'
            ], 500);
        }
    }

    /**
     * Get parent accounts for dropdown
     */
    public function getParentAccounts(Request $request)
    {
        try {
            $subType = $request->get('sub_type');
            $currentAccountId = $request->get('current_id', null);

            // Determine account type based on sub_type
            $accountType = 'asset';
            if (in_array($subType, ['payable', 'tax', 'equity'])) {
                $accountType = 'liability';
            } elseif (in_array($subType, ['sales', 'income', 'revenue'])) {
                $accountType = 'income';
            } elseif ($subType === 'expense') {
                $accountType = 'expense';
            }

            // Get parent accounts with matching sub_type AND parent_id IS NULL
            $query = ChartOfAccount::where('type', $accountType)
                ->where('sub_type', $subType)
                ->where('status', 'active')
                ->whereNull('parent_id');  // Only top-level parent accounts

            // Exclude current account
            if ($currentAccountId) {
                $query->where('id', '!=', $currentAccountId);
            }

            $accounts = $query->orderBy('name', 'asc')->get(['id', 'name']);

            \Log::info('Parent accounts loaded:', [
                'sub_type' => $subType,
                'accountType' => $accountType,
                'count' => $accounts->count(),
                'accounts' => $accounts->toArray()
            ]);

            return response()->json([
                'success' => true,
                'accounts' => $accounts
            ]);
        } catch (\Exception $e) {
            Log::error('Get parent accounts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'accounts' => []
            ], 500);
        }
    }

    /**
     * Generate account code
     */

    private function generateAccountCode($subType, $type)
    {
        $prefixes = [
            // Asset prefixes
            'cash' => '1-110',
            'bank' => '1-120',
            'mobile_bank' => '1-130',
            'receivable' => '1-140',
            'inventory' => '1-150',

            // Liability prefixes
            'payable' => '2-210',
            'tax' => '2-220',
            'equity' => '2-230',      // Equity under liability

            // Income prefixes
            'sales' => '4-410',
            'income' => '4-420',
            'revenue' => '4-430',

            // Expense prefixes
            'expense' => '5-510',
        ];

        $typePrefixes = [
            'asset' => '1-100',
            'liability' => '2-200',
            'income' => '4-400',
            'expense' => '5-500',
        ];

        $prefix = $prefixes[$subType] ?? $typePrefixes[$type] ?? '1-100';

        // Get existing codes
        $existingCodes = ChartOfAccount::where('code', 'like', $prefix . '%')
            ->withTrashed()
            ->pluck('code')
            ->map(function ($code) use ($prefix) {
                return intval(substr($code, strlen($prefix)));
            })
            ->sort()
            ->values()
            ->toArray();

        // Find next available number
        $newNumber = 1;
        $maxNumber = !empty($existingCodes) ? max($existingCodes) + 1 : 1;

        for ($i = 1; $i <= $maxNumber; $i++) {
            if (!in_array($i, $existingCodes)) {
                $newNumber = $i;
                break;
            }
        }

        return $prefix . str_pad($newNumber, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Create opening balance log
     */
    private function createOpeningBalanceLog($account, $asOfDate)
    {
        AccountStatementLog::create([
            'account_id' => $account->id,
            'transaction_date' => $asOfDate,
            'reference_no' => 'OPENING',
            'transaction_type' => 'opening_balance',
            'debit' => $account->type === 'asset' ? $account->opening_balance : 0,
            'credit' => $account->type === 'liability' || $account->type === 'equity' ? $account->opening_balance : 0,
            'balance' => $account->opening_balance,
            'description' => 'Opening Balance',
        ]);
    }

    /**
     * Update Owners Equity account when a new account is created with opening_balance
     * Asset/Expense: Cr Equity (equity increases)
     * Liability/Income: Dr Equity (equity decreases)
     */
    private function updateOwnersEquityForOpening($sourceAccount, $asOfDate)
    {
        $equityAccount = ChartOfAccount::where('type', 'liability')
            ->where('sub_type', 'equity')
            ->where('name', 'Owners Equity')
            ->first();

        if (!$equityAccount) return;

        $amount = $sourceAccount->opening_balance;
        $isDebitType = in_array($sourceAccount->type, ['asset', 'expense']);

        $equityAdjustment = $isDebitType ? $amount : -$amount;
        $equityAccount->current_balance += $equityAdjustment;

        AccountStatementLog::create([
            'account_id' => $equityAccount->id,
            'transaction_date' => $asOfDate,
            'reference_no' => 'OPENING',
            'transaction_type' => 'opening_balance',
            'debit' => $equityAdjustment < 0 ? abs($equityAdjustment) : 0,
            'credit' => $equityAdjustment > 0 ? $equityAdjustment : 0,
            'balance' => $equityAccount->current_balance,
            'description' => "Opening balance transfer - {$sourceAccount->name} ({$sourceAccount->code})",
        ]);

        $equityAccount->save();
    }

    /**
     * Update Owners Equity account when opening_balance is adjusted
     */
    private function updateOwnersEquityForAdjustment($sourceAccount, $difference, $asOfDate, $oldBalance, $newBalance)
    {
        if ($difference == 0) return;

        $equityAccount = ChartOfAccount::where('type', 'liability')
            ->where('sub_type', 'equity')
            ->where('name', 'Owners Equity')
            ->first();

        if (!$equityAccount) return;

        $isDebitType = in_array($sourceAccount->type, ['asset', 'expense']);
        $equityAdjustment = $isDebitType ? $difference : -$difference;
        $equityAccount->current_balance += $equityAdjustment;

        AccountStatementLog::create([
            'account_id' => $equityAccount->id,
            'transaction_date' => $asOfDate,
            'reference_no' => 'ADJUSTMENT',
            'transaction_type' => 'opening_balance_adjustment',
            'debit' => $equityAdjustment < 0 ? abs($equityAdjustment) : 0,
            'credit' => $equityAdjustment > 0 ? $equityAdjustment : 0,
            'balance' => $equityAccount->current_balance,
            'description' => "Opening balance adjustment - {$sourceAccount->name} ({$oldBalance} -> {$newBalance})",
        ]);

        $equityAccount->save();
    }
}
