<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\IncomeDetail;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\AccountStatementLog;
use App\Traits\StatementLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class IncomeController extends Controller
{
    use StatementLogTrait;
    // ============== LIST (with default sorting) ==============
    public function index(Request $request)
    {
        $query = Income::with(['receivedAccount', 'costCenter', 'creator', 'details.incomeAccount']);

        $sortField = $request->get('sort_field', 'date');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowed = ['date', 'voucher_no', 'total_amount'];
        if (in_array($sortField, $allowed)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('date', 'desc');
        }

        $incomes = $query->paginate(50);

        return view('backend.accounts.income.index', compact('incomes'));
    }

    // ============== HELPER – Statement Log ==============
    // private function insertStatementLog($accountId, $date, $refNo, $type, $debit, $credit, $desc, $source = null)
    // {
    //     $lastLog = AccountStatementLog::where('account_id', $accountId)
    //         ->orderBy('id', 'desc')
    //         ->lockForUpdate()
    //         ->first();

    //     $previousBalance = $lastLog ? $lastLog->balance : 0;
    //     $newBalance = $previousBalance + $debit - $credit; // debit increases, credit decreases

    //     return AccountStatementLog::create([
    //         'account_id'       => $accountId,
    //         'transaction_date' => $date,
    //         'reference_no'     => $refNo,
    //         'transaction_type' => $type,
    //         'debit'            => $debit,
    //         'credit'           => $credit,
    //         'balance'          => $newBalance,
    //         'description'      => $desc,
    //         'source_id'        => $source ? $source->id : null,
    //         'source_type'      => $source ? get_class($source) : null,
    //     ]);
    // }

    // ============== CREATE ==============
    public function create()
    {
        $receivedAccounts = ChartOfAccount::where('status', 'active')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('type', 'asset')
            ->orderBy('name')->get();

        $incomeAccounts = ChartOfAccount::where('status', 'active')
            ->where('type', 'income')
            ->whereNot('name', 'Courier Income')
            ->orderBy('name')
            ->get();

        $costCenters = CostCenter::where('status', 'active')->get();

        return view(
            'backend.accounts.income.create_edit',
            compact('receivedAccounts', 'incomeAccounts', 'costCenters')
        );
    }

    // ============== STORE ==============
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'                => 'required|date',
            'received_account_id' => 'required|exists:chart_of_accounts,id',
            'income_account_ids'  => 'required|array',
            'income_account_ids.*' => 'required|exists:chart_of_accounts,id',
            'amounts'             => 'required|array',
            'amounts.*'           => 'required|numeric|min:0.01',
            'note'                => 'nullable|string|max:500',
            'cost_center_id'      => 'nullable|exists:cost_centers,id',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $totalAmount = array_sum($request->amounts);

        try {
            DB::beginTransaction();

            $voucherNo = Income::generateVoucherNo();

            $income = Income::create([
                'voucher_no'          => $voucherNo,
                'date'                => $request->date,
                'received_account_id' => $request->received_account_id,
                'total_amount'        => $totalAmount,
                'note'                => $request->note,
                'cost_center_id'      => $request->cost_center_id,
                'created_by'          => auth()->id(),
                'status'              => Income::STATUS_APPROVED,
            ]);

            foreach ($request->income_account_ids as $index => $accountId) {
                IncomeDetail::create([
                    'income_id'         => $income->id,
                    'income_account_id' => $accountId,
                    'amount'            => $request->amounts[$index],
                    'note'              => $request->notes[$index] ?? null,
                ]);
            }

            // Update chart of accounts balances
            $income->updateAccountBalance();

            // ---- STATEMENT LOGS ----
            // 1. Received account (asset) – debit (increase)
            $this->insertStatementLog(
                $request->received_account_id,
                $request->date,
                $voucherNo,
                'income',
                $totalAmount,          // debit → money coming in
                0,
                $request->note ?? 'Income: ' . $voucherNo,
                $income
            );

            // 2. Income accounts – credit (increase)
            foreach ($request->income_account_ids as $index => $accountId) {
                $this->insertStatementLog(
                    $accountId,
                    $request->date,
                    $voucherNo,
                    'income',
                    0,
                    $request->amounts[$index],   // credit → income increases
                    $request->notes[$index] ?? 'Income: ' . $voucherNo,
                    $income
                );
            }

            DB::commit();

            if ($request->has('save_and_new')) {
                return redirect()->route('accounts.incomes.create')
                    ->with('success', 'Income created. Add another.');
            }
            return redirect()->route('accounts.incomes.index')
                ->with('success', 'Income created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    // ============== SHOW (AJAX) ==============
    public function show($id)
    {
        $income = Income::with(['receivedAccount', 'costCenter', 'creator', 'details.incomeAccount'])
            ->findOrFail($id);

        $html = view('backend.accounts.income.partials.view_modal', compact('income'))->render();
        return response()->json(['success' => true, 'html' => $html]);
    }

    // ============== EDIT ==============
    public function edit($id)
    {
        $income = Income::with('details')->findOrFail($id);

        $receivedAccounts = ChartOfAccount::where('status', 'active')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('type', 'asset')
            ->orderBy('name')->get();

        $incomeAccounts = ChartOfAccount::where('status', 'active')
            ->where('type', 'income')
            ->orderBy('name')->get();

        $costCenters = CostCenter::where('status', 'active')->get();

        return view(
            'backend.accounts.income.create_edit',
            compact('income', 'receivedAccounts', 'incomeAccounts', 'costCenters')
        );
    }

    // ============== UPDATE ==============
    public function update(Request $request, $id)
    {
        $income = Income::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'date'                => 'required|date',
            'received_account_id' => 'required|exists:chart_of_accounts,id',
            'income_account_ids'  => 'required|array',
            'income_account_ids.*' => 'required|exists:chart_of_accounts,id',
            'amounts'             => 'required|array',
            'amounts.*'           => 'required|numeric|min:0.01',
            'note'                => 'nullable|string|max:500',
            'cost_center_id'      => 'nullable|exists:cost_centers,id',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $totalAmount = array_sum($request->amounts);

        try {
            DB::beginTransaction();
            $income = Income::lockForUpdate()->findOrFail($id); // document first: re-read under its lock (a repeated/simultaneous update waits and sees the committed state)

            // Reverse old balances
            $income->reverseAccountBalance();

            // Insert reversal logs
            // Use the ORIGINAL income date so period reports net to zero
            $reversalDate = $income->date ? $income->date->toDateString() : now()->toDateString();
            $this->insertStatementLog(
                $income->received_account_id,
                $reversalDate,
                $income->voucher_no . '-REV',
                'income_reversal',
                0,                              // debit
                $income->total_amount,          // credit (reverse the original debit)
                'Reversal for edit of ' . $income->voucher_no,
                $income
            );
            foreach ($income->details as $oldDetail) {
                $this->insertStatementLog(
                    $oldDetail->income_account_id,
                    $reversalDate,
                    $income->voucher_no . '-REV',
                    'income_reversal',
                    $oldDetail->amount,         // debit (reverse the original credit)
                    0,
                    'Reversal for edit of ' . $income->voucher_no,
                    $income
                );
            }

            // Update record
            $income->update([
                'date'                => $request->date,
                'received_account_id' => $request->received_account_id,
                'total_amount'        => $totalAmount,
                'note'                => $request->note,
                'cost_center_id'      => $request->cost_center_id,
            ]);

            // Replace details
            IncomeDetail::where('income_id', $income->id)->delete();
            foreach ($request->income_account_ids as $index => $accountId) {
                IncomeDetail::create([
                    'income_id'         => $income->id,
                    'income_account_id' => $accountId,
                    'amount'            => $request->amounts[$index],
                    'note'              => $request->notes[$index] ?? null,
                ]);
            }

            // IMPORTANT: refresh the details relation - the previously cached
            // relation still holds the OLD details
            $income->unsetRelation('details');

            // Apply new balances
            $income->updateAccountBalance();

            // New statement logs
            $this->insertStatementLog(
                $request->received_account_id,
                $request->date,
                $income->voucher_no,
                'income',
                $totalAmount,
                0,
                $request->note ?? 'Income: ' . $income->voucher_no,
                $income
            );
            foreach ($request->income_account_ids as $index => $accountId) {
                $this->insertStatementLog(
                    $accountId,
                    $request->date,
                    $income->voucher_no,
                    'income',
                    0,
                    $request->amounts[$index],
                    $request->notes[$index] ?? 'Income: ' . $income->voucher_no,
                    $income
                );
            }

            DB::commit();

            return redirect()->route('accounts.incomes.index')
                ->with('success', 'Income updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage())->withInput();
        }
    }

    // ============== DELETE ==============
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $income = Income::lockForUpdate()->findOrFail($id); // document first (a repeated delete finds it gone)

            // Reverse balances
            $income->reverseAccountBalance();

            // Reversal logs
            // Use the ORIGINAL income date so period reports net to zero
            $reversalDate = $income->date ? $income->date->toDateString() : now()->toDateString();
            $this->insertStatementLog(
                $income->received_account_id,
                $reversalDate,
                $income->voucher_no . '-DEL',
                'income_deletion',
                0,
                $income->total_amount,
                'Deletion of income ' . $income->voucher_no,
                $income
            );
            foreach ($income->details as $detail) {
                $this->insertStatementLog(
                    $detail->income_account_id,
                    $reversalDate,
                    $income->voucher_no . '-DEL',
                    'income_deletion',
                    $detail->amount,
                    0,
                    'Deletion of income ' . $income->voucher_no,
                    $income
                );
            }

            $income->delete();

            DB::commit();

            if (request()->ajax()) {
                return response()->json(['success' => true, 'message' => 'Income deleted.']);
            }
            return redirect()->route('accounts.incomes.index')
                ->with('success', 'Income deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            if (request()->ajax()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // ============== FILTER (AJAX) ==============
    public function filter(Request $request)
    {
        $query = Income::with(['receivedAccount', 'costCenter', 'creator', 'details.incomeAccount']);

        if ($request->filled('income_account')) {
            $query->whereHas('details.incomeAccount', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->income_account . '%');
            });
        }
        if ($request->filled('deposit_to')) {
            $query->whereHas('receivedAccount', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->deposit_to . '%');
            });
        }
        if ($request->filled('date_option')) {
            switch ($request->date_option) {
                case 'this_month':
                    $query->whereMonth('date', now()->month)->whereYear('date', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('date', now()->subMonth()->month)
                        ->whereYear('date', now()->subMonth()->year);
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
            $query->whereHas('creator', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->prepared_by . '%');
            });
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

        $incomes = $query->paginate(50);

        if ($request->ajax()) {
            $tableHtml = view('backend.accounts.income.partials.table_rows', compact('incomes'))->render();
            $paginationHtml = $incomes->appends($request->except('page'))->links()->toHtml();
            return response()->json([
                'table_html'      => $tableHtml,
                'pagination_html' => $paginationHtml
            ]);
        }

        return view('backend.accounts.income.index', compact('incomes'));
    }

    // ============== PRINT ==============
    public function print($id)
    {
        $income = Income::with(['receivedAccount', 'costCenter', 'creator', 'details.incomeAccount'])
            ->findOrFail($id);
        return view('backend.accounts.income.print', compact('income'));
    }
}
