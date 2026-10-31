<?php

namespace App\Http\Controllers;

use App\Models\ExpensePayment;
use App\Models\ExpensePaymentDetail;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\AccountStatementLog;          // ← don't forget this
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpensePaymentController extends Controller
{

public function index(Request $request)
{
    $query = ExpensePayment::with(['paymentAccount', 'costCenter', 'creator', 'details.expenseAccount']);

    // Default sorting
    $sortField = $request->get('sort_field', 'date');
    $sortDirection = $request->get('sort_direction', 'desc');
    $query->orderBy($sortField, $sortDirection);

    $expensePayments = $query->paginate(50);

    return view('backend.accounts.payment_expense.index', compact('expensePayments'));
}
    // ========================
    //  HELPER – STATEMENT LOG
    // ========================
    /**
     * Insert a single account statement log entry.
     * Calculates running balance using the latest log for that account.
     */
    private function insertStatementLog(
        $accountId,
        $date,
        $referenceNo,
        $type,
        $debit,
        $credit,
        $desc,
        $sourceModel = null
    ) {
        $account = ChartOfAccount::find($accountId);
        $balance = $account ? $account->current_balance : 0;

        return AccountStatementLog::create([
            'account_id'       => $accountId,
            'transaction_date' => $date,
            'reference_no'     => $referenceNo,
            'transaction_type' => $type,
            'debit'            => $debit,
            'credit'           => $credit,
            'balance'          => $balance,
            'description'      => $desc,
            'source_id'        => $sourceModel ? $sourceModel->id : null,
            'source_type'      => $sourceModel ? get_class($sourceModel) : null,
        ]);
    }

    // ============
    //  CREATE
    // ============
    public function create()
    {
        $paymentAccounts = ChartOfAccount::where('status', 'active')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->orderBy('name')->get();

        $expenseAccounts = ChartOfAccount::where('status', 'active')
            ->where('type', 'expense')
            ->whereNotIn('name', ['Courier Fee Expense', 'Courier Settlement Expense'])
            ->orderBy('name')
            ->get();

        $costCenters = CostCenter::where('status', 'active')->get();

        return view(
            'backend.accounts.payment_expense.create_edit',
            compact('paymentAccounts', 'expenseAccounts', 'costCenters')
        );
    }

    // ============
    //  STORE
    // ============
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'                  => 'required|date',
            'payment_account_id'    => 'required|exists:chart_of_accounts,id',
            'expense_account_ids'   => 'required|array',
            'expense_account_ids.*' => 'required|exists:chart_of_accounts,id',
            'amounts'              => 'required|array',
            'amounts.*'            => 'required|numeric|min:0.01',
            'note'                 => 'nullable|string|max:500',
            'cost_center_id'       => 'nullable|exists:cost_centers,id',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $totalAmount = array_sum($request->amounts);

        // Validate balance
        $paymentAccount = ChartOfAccount::find($request->payment_account_id);
        if ($paymentAccount && $paymentAccount->current_balance < $totalAmount) {
            if ($request->ajax()) {
                return response()->json(['error' => 'Insufficient balance in account'], 422);
            }
            return redirect()->back()->with('error', 'Insufficient balance in account')->withInput();
        }

        try {
            DB::beginTransaction();

            $voucherNo = ExpensePayment::generateVoucherNo();

            // Create the main expense payment record
            $expensePayment = ExpensePayment::create([
                'voucher_no'         => $voucherNo,
                'date'               => $request->date,
                'payment_account_id' => $request->payment_account_id,
                'total_amount'       => $totalAmount,
                'note'               => $request->note,
                'cost_center_id'     => $request->cost_center_id,
                'created_by'         => auth()->id(),
                'status'             => ExpensePayment::STATUS_APPROVED,
            ]);

            // Create details
            foreach ($request->expense_account_ids as $index => $accountId) {
                ExpensePaymentDetail::create([
                    'expense_payment_id' => $expensePayment->id,
                    'expense_account_id' => $accountId,
                    'amount'             => $request->amounts[$index],
                    'note'               => $request->notes[$index] ?? null,
                ]);
            }

            // Update chart_of_accounts balances
            $expensePayment->updateAccountBalance();

            // ---------- INSERT STATEMENT LOGS ----------
            // 1. Payment account (cash/bank) – credit entry
            $this->insertStatementLog(
                $request->payment_account_id,
                $request->date,
                $voucherNo,
                'expense_payment',
                0,                // debit
                $totalAmount,     // credit → money goes out
                $request->note ?? 'Expense payment: ' . $voucherNo,
                $expensePayment
            );

            // 2. Expense accounts – debit entries
            foreach ($request->expense_account_ids as $index => $accountId) {
                $this->insertStatementLog(
                    $accountId,
                    $request->date,
                    $voucherNo,
                    'expense',
                    $request->amounts[$index],   // debit → expense incurred
                    0,                            // credit
                    $request->notes[$index] ?? 'Expense: ' . $voucherNo,
                    $expensePayment
                );
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Expense payment created successfully',
                    'redirect' => url()->previous()
                ]);
            }

            return redirect()->back()
                ->with('success', 'Expense payment created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->ajax()) {
                return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    // ============
    //  SHOW (AJAX)
    // ============
    public function show($id)
    {
        $expensePayment = ExpensePayment::with(['paymentAccount', 'costCenter', 'creator', 'details.expenseAccount'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'html' => view('backend.accounts.payment_expense.partials.view_modal', compact('expensePayment'))->render()
        ]);
    }

    // ============
    //  EDIT
    // ============
    public function edit($id)
    {
        $expensePayment = ExpensePayment::with('details')->findOrFail($id);

        $paymentAccounts = ChartOfAccount::where('status', 'active')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->orderBy('name')->get();

        $expenseAccounts = ChartOfAccount::where('status', 'active')
            ->where('type', 'expense')
            ->orderBy('name')->get();

        $costCenters = CostCenter::where('status', 'active')->get();

        return view(
            'backend.accounts.payment_expense.create_edit',
            compact('expensePayment', 'paymentAccounts', 'expenseAccounts', 'costCenters')
        );
    }

    // ============
    //  UPDATE
    // ============
    public function update(Request $request, $id)
    {
        $expensePayment = ExpensePayment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'date'                  => 'required|date',
            'payment_account_id'    => 'required|exists:chart_of_accounts,id',
            'expense_account_ids'   => 'required|array',
            'expense_account_ids.*' => 'required|exists:chart_of_accounts,id',
            'amounts'              => 'required|array',
            'amounts.*'            => 'required|numeric|min:0.01',
            'note'                 => 'nullable|string|max:500',
            'cost_center_id'       => 'nullable|exists:cost_centers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $totalAmount = array_sum($request->amounts);

        // Validate balance (including old amount if account unchanged)
        $paymentAccount = ChartOfAccount::find($request->payment_account_id);
        $currentBalance = $paymentAccount->current_balance;
        if ($expensePayment->payment_account_id == $request->payment_account_id) {
            $currentBalance += $expensePayment->total_amount;
        }
        if ($currentBalance < $totalAmount) {
            return redirect()->back()->with('error', 'Insufficient balance in account')->withInput();
        }

        try {
            DB::beginTransaction();

            // ---- REVERSE OLD TRANSACTIONS ----
            // 1. Reverse chart_of_accounts balances
            $expensePayment->reverseAccountBalance();

            // 2. Insert reversal logs
            // Use the ORIGINAL expense date so period reports net to zero
            $reversalDate = $expensePayment->date ? $expensePayment->date->toDateString() : now()->toDateString();
            // Payment account reversal
            $this->insertStatementLog(
                $expensePayment->payment_account_id,
                $reversalDate,
                $expensePayment->voucher_no . '-REV',
                'expense_payment_reversal',
                $expensePayment->total_amount,   // debit to reverse previous credit
                0,
                'Reversal for edit of ' . $expensePayment->voucher_no,
                $expensePayment
            );
            // Old expense account reversals
            foreach ($expensePayment->details as $oldDetail) {
                $this->insertStatementLog(
                    $oldDetail->expense_account_id,
                    $reversalDate,
                    $expensePayment->voucher_no . '-REV',
                    'expense_reversal',
                    0,                      // debit
                    $oldDetail->amount,     // credit to reverse previous debit
                    'Reversal for edit of ' . $expensePayment->voucher_no,
                    $expensePayment
                );
            }

            // ---- UPDATE RECORD ----
            $expensePayment->update([
                'date'               => $request->date,
                'payment_account_id' => $request->payment_account_id,
                'total_amount'       => $totalAmount,
                'note'               => $request->note,
                'cost_center_id'     => $request->cost_center_id,
            ]);

            // Replace details
            ExpensePaymentDetail::where('expense_payment_id', $expensePayment->id)->delete();
            foreach ($request->expense_account_ids as $index => $accountId) {
                ExpensePaymentDetail::create([
                    'expense_payment_id' => $expensePayment->id,
                    'expense_account_id' => $accountId,
                    'amount'             => $request->amounts[$index],
                    'note'               => $request->notes[$index] ?? null,
                ]);
            }

            // IMPORTANT: refresh the details relation - the previously cached
            // relation still holds the OLD details
            $expensePayment->unsetRelation('details');

            // Apply new balances to chart_of_accounts
            $expensePayment->updateAccountBalance();

            // ---- NEW STATEMENT LOGS ----
            // Payment account
            $this->insertStatementLog(
                $request->payment_account_id,
                $request->date,
                $expensePayment->voucher_no,
                'expense_payment',
                0,
                $totalAmount,
                $request->note ?? 'Expense payment: ' . $expensePayment->voucher_no,
                $expensePayment
            );
            // Expense accounts
            foreach ($request->expense_account_ids as $index => $accountId) {
                $this->insertStatementLog(
                    $accountId,
                    $request->date,
                    $expensePayment->voucher_no,
                    'expense',
                    $request->amounts[$index],
                    0,
                    $request->notes[$index] ?? 'Expense: ' . $expensePayment->voucher_no,
                    $expensePayment
                );
            }

            DB::commit();

            return redirect()->route('accounts.expense-payments.index')
                ->with('success', 'Expense payment updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error updating: ' . $e->getMessage())->withInput();
        }
    }

    // ============
    //  DELETE
    // ============
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $expensePayment = ExpensePayment::lockForUpdate()->findOrFail($id); // document first (a repeated delete finds it gone)

            // Reverse chart_of_accounts balances
            $expensePayment->reverseAccountBalance();

            // Insert reversal logs
            // Use the ORIGINAL expense date so period reports net to zero
            $reversalDate = $expensePayment->date ? $expensePayment->date->toDateString() : now()->toDateString();
            // Payment account
            $this->insertStatementLog(
                $expensePayment->payment_account_id,
                $reversalDate,
                $expensePayment->voucher_no . '-DEL',
                'expense_payment_deletion',
                $expensePayment->total_amount,   // debit to add money back to cash
                0,
                'Deletion of expense payment ' . $expensePayment->voucher_no,
                $expensePayment
            );
            // Expense accounts
            foreach ($expensePayment->details as $detail) {
                $this->insertStatementLog(
                    $detail->expense_account_id,
                    $reversalDate,
                    $expensePayment->voucher_no . '-DEL',
                    'expense_deletion',
                    0,
                    $detail->amount,             // credit to reverse the expense
                    'Deletion of expense payment ' . $expensePayment->voucher_no,
                    $expensePayment
                );
            }

            // Delete the payment (cascade deletes details)
            $expensePayment->delete();

            DB::commit();

            if (request()->ajax()) {
                return response()->json(['success' => true, 'message' => 'Expense payment deleted.']);
            }

            return redirect()->route('accounts.expense-payments.index')
                ->with('success', 'Expense payment deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            if (request()->ajax()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Error deleting: ' . $e->getMessage());
        }
    }

    // ============
    //  FILTER
    // ============
public function filter(Request $request)
{
    $query = ExpensePayment::with(['paymentAccount', 'costCenter', 'creator', 'details.expenseAccount']);

    // Filters
    if ($request->filled('pay_from')) {
        $query->whereHas('paymentAccount', function($q) use ($request) {
            $q->where('name', 'like', '%' . $request->pay_from . '%');
        });
    }
    if ($request->filled('expense')) {
        $query->whereHas('details.expenseAccount', function($q) use ($request) {
            $q->where('name', 'like', '%' . $request->expense . '%');
        });
    }
    if ($request->filled('date_option')) {
        switch ($request->date_option) {
            case 'this_month':
                $query->whereMonth('date', now()->month)->whereYear('date', now()->year);
                break;
            case 'last_month':
                $query->whereMonth('date', now()->subMonth()->month)->whereYear('date', now()->subMonth()->year);
                break;
            case 'custom':
                if ($request->filled('from_date')) {
                    $query->whereDate('date', '>=', $request->from_date);
                }
                if ($request->filled('to_date')) {
                    $query->whereDate('date', '<=', $request->to_date);
                }
                break;
        }
    }
    if ($request->filled('prepared_by')) {
        $query->whereHas('creator', function($q) use ($request) {
            $q->where('name', 'like', '%' . $request->prepared_by . '%');
        });
    }
    if ($request->filled('cost_center_id')) {
        $query->where('cost_center_id', $request->cost_center_id);
    }

    // Sorting
    $sortField = $request->get('sort_field', 'date');
    $sortDirection = $request->get('sort_direction', 'desc');
    $allowed = ['date', 'voucher_no', 'total_amount'];
    if (in_array($sortField, $allowed)) {
        $query->orderBy($sortField, $sortDirection);
    } else {
        $query->orderBy('date', 'desc');
    }

    $expensePayments = $query->paginate(50);

    if ($request->ajax()) {
        $tableHtml = view('backend.accounts.payment_expense.partials.table_rows', compact('expensePayments'))->render();
        $paginationHtml = $expensePayments->appends($request->except('page'))->links()->toHtml();
        return response()->json([
            'table_html'      => $tableHtml,
            'pagination_html' => $paginationHtml
        ]);
    }

    return view('backend.accounts.payment_expense.index', compact('expensePayments'));
}

    // ============
    //  PRINT
    // ============
    public function print($id)
    {
        $expensePayment = ExpensePayment::with(['paymentAccount', 'costCenter', 'creator', 'details.expenseAccount'])
            ->findOrFail($id);
        return view('backend.accounts.payment_expense.print', compact('expensePayment'));
    }
}
