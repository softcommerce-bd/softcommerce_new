<?php

namespace App\Http\Controllers;

use App\Models\BalanceTransfer;
use App\Models\ChartOfAccount;
use App\Traits\StatementLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BalanceTransferController extends Controller
{
    use StatementLogTrait;

    public function index(Request $request)
    {
        $query = BalanceTransfer::with(['fromAccount', 'toAccount', 'creator', 'costCenter']);
        $query->filter($request->only(['from_account', 'to_account', 'voucher_no', 'transaction_type', 'from_date', 'to_date']));

        $sortField = $request->get('sort', 'id');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $transactions = $query->paginate(50)->appends($request->all());

        if ($request->ajax()) {
            return view('backend.accounts.withdraw_transfer.table_rows', compact('transactions'));
        }

        return view('backend.accounts.withdraw_transfer.index', compact('transactions'));
    }

    public function create(Request $request)
    {
        $accounts = ChartOfAccount::where('status', 'active')
            ->select('id', 'name', 'code', 'sub_type', 'current_balance', 'type')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('backend.accounts.withdraw_transfer.create_edit', compact('accounts'));
    }

    public function edit($id, Request $request)
    {
        $transaction = BalanceTransfer::findOrFail($id);
        $accounts = ChartOfAccount::where('status', 'active')
            ->select('id', 'name', 'code', 'sub_type', 'current_balance', 'type')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return view('backend.accounts.withdraw_transfer.create_edit', compact('accounts', 'transaction'));
    }

    public function store(Request $request)
    {
         \Log::info('STORE CALLED', [
        'url' => request()->fullUrl(),
        'method' => request()->method(),
        'amount' => $request->amount,
        'from' => $request->from_account_id,
        'to' => $request->to_account_id,
        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
    ]);
        $validator = Validator::make($request->all(), [
            'transaction_type' => 'required|in:withdraw,deposit,transfer,cashout',
            'date' => 'required|date',
            'from_account_id' => 'required|exists:chart_of_accounts,id',
            'to_account_id' => 'required|exists:chart_of_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'charge' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
            'reference_no' => 'nullable|string',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if ($request->transaction_type === 'transfer' && $request->from_account_id == $request->to_account_id) {
            return redirect()->back()->withErrors(['to_account_id' => 'Cannot transfer to the same account'])->withInput();
        }

        $fromAccount = ChartOfAccount::findOrFail($request->from_account_id);
        $totalAmount = $request->amount + ($request->charge ?? 0);
        
        if (in_array($request->transaction_type, ['withdraw', 'transfer', 'cashout'])) {
            if ($fromAccount->current_balance < $totalAmount) {
                return redirect()->back()->withErrors(['amount' => 'Insufficient balance in source account. Available: ' . number_format($fromAccount->current_balance, 2)])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $this->lockAccountsAscending($request->from_account_id, $request->to_account_id); // both accounts, ascending id, before anything else
            // the same rule again, now under the source account's row lock: a concurrent withdrawal/transfer that committed first is seen
            if (in_array($request->transaction_type, ['withdraw', 'transfer', 'cashout'])) {
                $fromAccount = ChartOfAccount::lockForUpdate()->findOrFail($request->from_account_id);
                if ($fromAccount->current_balance < $totalAmount) {
                    DB::rollBack();
                    return redirect()->back()->withErrors(['amount' => 'Insufficient balance in source account. Available: ' . number_format($fromAccount->current_balance, 2)])->withInput();
                }
            }
            $voucherNo = BalanceTransfer::generateVoucherNo($request->transaction_type);

            $transaction = BalanceTransfer::create([
                'voucher_no' => $voucherNo,
                'date' => $request->date,
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'amount' => $request->amount,
                'charge' => $request->charge ?? 0,
                'transaction_type' => $request->transaction_type,
                'reference_no' => $request->reference_no,
                'cost_center_id' => $request->cost_center_id,
                'note' => $request->note,
                'created_by' => auth()->id(),
                'status' => 'completed',
            ]);

            // STEP 1: Update account balances
            $this->updateAccountBalances($transaction);
            
            // STEP 2: Insert statement logs (uses updated account balance from trait)
            $this->insertTransactionLogs($transaction);

            DB::commit();

            if ($request->has('save_and_new')) {
                return redirect()->route('accounts.balance-transfers.create')->with('success', 'Transaction created successfully.');
            }

            return redirect()->route('accounts.index', ['tab' => 'withdraw-deposit'])->with('success', 'Transaction created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Balance transfer store error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        $transaction = BalanceTransfer::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'transaction_type' => 'required|in:withdraw,deposit,transfer,cashout',
            'date' => 'required|date',
            'from_account_id' => 'required|exists:chart_of_accounts,id',
            'to_account_id' => 'required|exists:chart_of_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'charge' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
            'reference_no' => 'nullable|string',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        if ($request->transaction_type === 'transfer' && $request->from_account_id == $request->to_account_id) {
            return redirect()->back()->withErrors(['to_account_id' => 'Cannot transfer to the same account'])->withInput();
        }

        DB::beginTransaction();
        try {
            $transaction = BalanceTransfer::lockForUpdate()->findOrFail($id); // document first: re-read under its lock (a repeated/simultaneous update waits and sees the committed state)
            // STEP 1: Insert reversal logs
            $this->insertReversalLogs($transaction, 'edit');
            
            // STEP 2: Revert old balances
            $this->revertAccountBalances($transaction);

            // STEP 3: Update transaction
            $transaction->update([
                'date' => $request->date,
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'amount' => $request->amount,
                'charge' => $request->charge ?? 0,
                'transaction_type' => $request->transaction_type,
                'reference_no' => $request->reference_no,
                'cost_center_id' => $request->cost_center_id,
                'note' => $request->note,
            ]);

            // STEP 4: Apply new balances
            $this->updateAccountBalances($transaction);
            
            // STEP 5: Insert new logs
            $this->insertTransactionLogs($transaction);

            DB::commit();
            
            return redirect()->route('accounts.index', ['tab' => 'withdraw-deposit'])->with('success', 'Transaction updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Balance transfer update error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        $transaction = BalanceTransfer::findOrFail($id);

        DB::beginTransaction();
        try {
            $transaction = BalanceTransfer::lockForUpdate()->findOrFail($id); // document first: re-read under the lock (a repeated delete finds it gone)
            // STEP 1: Insert deletion reversal logs
            $this->insertReversalLogs($transaction, 'delete');
            
            // STEP 2: Revert account balances
            $this->revertAccountBalances($transaction);

            // STEP 3: Delete transaction
            $transaction->delete();

            DB::commit();
            
            return response()->json(['success' => true, 'message' => 'Transaction deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Balance transfer delete error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $transaction = BalanceTransfer::with(['fromAccount', 'toAccount', 'creator', 'costCenter'])->findOrFail($id);
        return view('backend.accounts.withdraw_transfer.show', compact('transaction'));
    }

    public function print($id)
    {
        $transaction = BalanceTransfer::with(['fromAccount', 'toAccount', 'creator', 'costCenter'])->findOrFail($id);
        return view('backend.accounts.withdraw_transfer.print', compact('transaction'));
    }

    public function filter(Request $request)
    {
        return $this->index($request);
    }

    // ================== PRIVATE HELPERS ==================

    // private function updateAccountBalances(BalanceTransfer $transaction)
    // {
    //     $fromAccount = ChartOfAccount::find($transaction->from_account_id);
    //     $toAccount = ChartOfAccount::find($transaction->to_account_id);
        
    //     $totalDeduction = $transaction->amount + $transaction->charge;

    //     switch ($transaction->transaction_type) {
    //         case 'withdraw':
    //         case 'transfer':
    //         case 'cashout':
    //             $fromAccount->current_balance -= $totalDeduction;
    //             $toAccount->current_balance += $transaction->amount;
    //             break;
    //         case 'deposit':
    //             $fromAccount->current_balance -= $transaction->amount;
    //             $toAccount->current_balance += $transaction->amount;
    //             break;
    //     }

    //     $fromAccount->save();
    //     $toAccount->save();
    // }

    /** Locks the accounts of a transfer in ascending id order whatever the direction, so A->B and B->A can never wait for each other (T-5 lock order). */
    private function lockAccountsAscending(...$ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);
        if ($ids !== []) {
            ChartOfAccount::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
        }
    }

    private function updateAccountBalances(BalanceTransfer $transaction)
{
    $this->lockAccountsAscending($transaction->from_account_id, $transaction->to_account_id);
    $fromAccount = ChartOfAccount::where('id', $transaction->from_account_id)
        ->lockForUpdate()
        ->first();
    $toAccount = ChartOfAccount::where('id', $transaction->to_account_id)
        ->lockForUpdate()
        ->first();
    
    if (!$fromAccount || !$toAccount) {
        throw new \Exception('Account not found');
    }

    $totalDeduction = $transaction->amount + $transaction->charge;

    \Log::info('BEFORE Balance Update', [
        'voucher_no' => $transaction->voucher_no,
        'from_id' => $fromAccount->id,
        'from_name' => $fromAccount->name,
        'from_balance' => $fromAccount->current_balance,
        'to_id' => $toAccount->id,
        'to_name' => $toAccount->name,
        'to_balance' => $toAccount->current_balance,
        'amount' => $transaction->amount,
        'charge' => $transaction->charge,
        'total_deduction' => $totalDeduction
    ]);

    switch ($transaction->transaction_type) {
        case 'withdraw':
        case 'transfer':
        case 'cashout':
            $fromAccount->current_balance -= $totalDeduction;
            $toAccount->current_balance += $transaction->amount;
            break;
        case 'deposit':
            $fromAccount->current_balance -= $transaction->amount;
            $toAccount->current_balance += $transaction->amount;
            break;
    }

    $fromAccount->save();
    $toAccount->save();

    \Log::info('AFTER Balance Update', [
        'voucher_no' => $transaction->voucher_no,
        'from_balance' => $fromAccount->current_balance,
        'to_balance' => $toAccount->current_balance
    ]);
}

    private function revertAccountBalances(BalanceTransfer $transaction)
    {
    $this->lockAccountsAscending($transaction->from_account_id, $transaction->to_account_id);
        $fromAccount = ChartOfAccount::find($transaction->from_account_id);
        $toAccount = ChartOfAccount::find($transaction->to_account_id);
        
        $totalDeduction = $transaction->amount + $transaction->charge;

        switch ($transaction->transaction_type) {
            case 'withdraw':
            case 'transfer':
            case 'cashout':
                $fromAccount->current_balance += $totalDeduction;
                $toAccount->current_balance -= $transaction->amount;
                break;
            case 'deposit':
                $fromAccount->current_balance += $transaction->amount;
                $toAccount->current_balance -= $transaction->amount;
                break;
        }

        $fromAccount->save();
        $toAccount->save();
    }

    private function insertTransactionLogs(BalanceTransfer $transaction)
    {
        $fromAccount = $transaction->fromAccount;
        $toAccount = $transaction->toAccount;
        $totalDeduction = $transaction->amount + $transaction->charge;

        $typeLabels = [
            'withdraw' => 'Withdrawal',
            'deposit' => 'Deposit',
            'transfer' => 'Transfer',
            'cashout' => 'Cashout'
        ];
        $typeLabel = $typeLabels[$transaction->transaction_type] ?? $transaction->transaction_type;

        // Source account: CREDIT (money going out)
        $this->insertStatementLog(
            $transaction->from_account_id,
            $transaction->date,
            $transaction->voucher_no,
            $transaction->transaction_type,
            0,
            $totalDeduction,
            $typeLabel . ': ' . $fromAccount->name . ' → ' . $toAccount->name . ' | ' . number_format($transaction->amount, 2),
            $transaction
        );

        // Destination account: DEBIT (money coming in)
        $this->insertStatementLog(
            $transaction->to_account_id,
            $transaction->date,
            $transaction->voucher_no,
            $transaction->transaction_type,
            $transaction->amount,
            0,
            $typeLabel . ': ' . $fromAccount->name . ' → ' . $toAccount->name . ' | ' . number_format($transaction->amount, 2),
            $transaction
        );
    }

    private function insertReversalLogs(BalanceTransfer $transaction, string $action = 'edit')
    {
        $suffix = $action === 'edit' ? '-REV' : '-DEL';
        $typeSuffix = $action === 'edit' ? '_reversal' : '_deletion';
        $actionLabel = $action === 'edit' ? 'Update' : 'Deletion';

        $fromAccount = $transaction->fromAccount;
        $toAccount = $transaction->toAccount;
        $totalDeduction = $transaction->amount + $transaction->charge;

        // Reverse source: DEBIT (money coming back)
        // Use the ORIGINAL transaction date so period reports net to zero
        $reversalDate = $transaction->date ? date('Y-m-d', strtotime($transaction->date)) : now()->toDateString();
        $this->insertStatementLog(
            $transaction->from_account_id,
            $reversalDate,
            $transaction->voucher_no . $suffix,
            $transaction->transaction_type . $typeSuffix,
            $totalDeduction,
            0,
            'REVERSAL (' . $actionLabel . '): ' . $transaction->voucher_no . ' | ' . number_format($totalDeduction, 2),
            $transaction
        );

        // Reverse destination: CREDIT (money going back)
        $this->insertStatementLog(
            $transaction->to_account_id,
            $reversalDate,
            $transaction->voucher_no . $suffix,
            $transaction->transaction_type . $typeSuffix,
            0,
            $transaction->amount,
            'REVERSAL (' . $actionLabel . '): ' . $transaction->voucher_no . ' | ' . number_format($transaction->amount, 2),
            $transaction
        );
    }
}