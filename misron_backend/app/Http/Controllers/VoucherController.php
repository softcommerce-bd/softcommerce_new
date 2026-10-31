<?php

namespace App\Http\Controllers;

use App\Models\AccountStatementLog;
use App\Models\Voucher;
use App\Models\VoucherDetail;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Traits\StatementLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Mpdf\Mpdf;

class VoucherController extends Controller
{
  use StatementLogTrait;
    // ================== PRIVATE HELPER ==================
    // private function insertStatementLog(
    //     $accountId,
    //     $date,
    //     $referenceNo,
    //     $type,
    //     $debit,
    //     $credit,
    //     $desc,
    //     $sourceModel = null
    // ) {
    //     $lastLog = AccountStatementLog::where('account_id', $accountId)
    //         ->orderBy('id', 'desc')
    //         ->lockForUpdate()
    //         ->first();

    //     $previousBalance = $lastLog ? $lastLog->balance : 0;
    //     $newBalance = $previousBalance + $debit - $credit;

    //     return AccountStatementLog::create([
    //         'account_id'       => $accountId,
    //         'transaction_date' => $date,
    //         'reference_no'     => $referenceNo,
    //         'transaction_type' => $type,
    //         'debit'            => $debit,
    //         'credit'           => $credit,
    //         'balance'          => $newBalance,
    //         'description'      => $desc,
    //         'source_id'        => $sourceModel ? $sourceModel->id : null,
    //         'source_type'      => $sourceModel ? get_class($sourceModel) : null,
    //     ]);
    // }
    public function index(Request $request)
    {
        $vouchers = Voucher::with(['creator', 'details.account'])
            ->latest()
            ->paginate(20);

        return view('backend.accounts.vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        $costCenters = CostCenter::where('status', 'active')->get();
        return view('backend.accounts.vouchers.create_edit', compact('costCenters'));
    }

    public function getAccountsByType(Request $request)
    {
        $voucherType = $request->voucher_type;

        $query = ChartOfAccount::where('status', 'active')
            ->whereNotNull('sub_type')
            ->where('sub_type', '!=', '');

        // Dizi Cashier Logic for table select accounts
        switch ($voucherType) {
            case 'CP': // Cash Payment - Show: Expense, Payable, Equity
            case 'BP': // Bank Payment - Show: Expense, Payable, Equity
            // case 'CR': // Cash Receipt - Show: Expense, Payable, Equity
            // case 'BR': // Bank Receipt - Show: Expense, Payable, Equity
                $query->where(function ($q) {
                    $q->where('type', 'expense')           // All expense accounts
                        ->orWhere('sub_type', 'payable')      // Accounts Payable
                        ->orWhere('sub_type', 'equity');      // Equity accounts (Current Year Earnings, Opening Balance, Owners Equity, Retained Earnings)
                });
                break;

            case 'JV': // Journal Voucher - Show: ALL accounts
            case 'CV': // Contra Voucher - Show: ALL accounts
                $query->whereIn('type', ['asset', 'liability', 'equity', 'income', 'expense'])
                    ->whereNotNull('sub_type')
                    ->where('sub_type', '!=', '');
                break;

            default:
                $query->whereNotNull('sub_type');
                break;
        }

        // Exclude parent group accounts (Liabilities, Expenses, Income etc. that have no sub_type)
        $query->where(function ($q) {
            $q->whereNotNull('sub_type')
                ->where('sub_type', '!=', '');
        });

        $accounts = $query->orderBy('type')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'accounts' => $accounts
        ]);
    }

    /**
     * Get cash/bank accounts for the top dropdown (CP, BP, CR, BR)
     */
    public function getCashBankAccounts(Request $request)
    {
        $voucherType = $request->voucher_type;

        $query = ChartOfAccount::where('status', 'active')
            ->where('type', 'asset');

        // For CP and CR: Show only Cash accounts
        // For BP and BR: Show only Bank & Mobile Bank accounts
        if ($voucherType == 'CP' || $voucherType == 'CR') {
            $query->where('sub_type', 'cash');
        } elseif ($voucherType == 'BP' || $voucherType == 'BR') {
            $query->whereIn('sub_type', ['bank', 'mobile_bank']);
        }

        $accounts = $query->orderBy('name')->get(['id', 'name', 'code', 'current_balance']);

        return response()->json([
            'success' => true,
            'accounts' => $accounts
        ]);
    }


    /**
     * Store voucher - Exact Dizi Cashier Logic
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'type' => 'required|in:CP,BP,CR,BR,JV,CV',
            'account_ids' => 'required|array|min:1',
            'account_ids.*' => 'exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'reference_no' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $isJournalOrContra = in_array($request->type, ['JV', 'CV']);

        try {
            DB::beginTransaction();

            $voucherEntries = [];

            if ($isJournalOrContra) {
                // For JV and CV: Use debits[] and credits[] arrays
                $validator = Validator::make($request->all(), [
                    'debits' => 'required|array',
                    'credits' => 'required|array',
                ]);

                if ($validator->fails()) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $totalDebit = array_sum($request->debits);
                $totalCredit = array_sum($request->credits);

                if ($totalDebit != $totalCredit) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->with('error', 'Total debit must equal total credit')->withInput();
                }

                if ($totalDebit == 0) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->with('error', 'Amount cannot be zero')->withInput();
                }

                // Add table entries with debit/credit
                foreach ($request->account_ids as $index => $accountId) {
                    if (($request->debits[$index] ?? 0) > 0 || ($request->credits[$index] ?? 0) > 0) {
                        $voucherEntries[] = [
                            'account_id' => $accountId,
                            'debit' => $request->debits[$index] ?? 0,
                            'credit' => $request->credits[$index] ?? 0,
                            'narration' => $request->narrations[$index] ?? null,
                        ];
                    }
                }
            } else {
                // For CP, BP, CR, BR: Use amounts[] array (single amount column)
                $validator = Validator::make($request->all(), [
                    'amounts' => 'required|array',
                    'amounts.*' => 'numeric|min:0',
                    'cash_bank_account_id' => 'required|exists:chart_of_accounts,id',
                ]);

                if ($validator->fails()) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $totalAmount = array_sum($request->amounts);

                if ($totalAmount == 0) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->with('error', 'Amount cannot be zero')->withInput();
                }

                $cashBankAccountId = $request->cash_bank_account_id;

                // Add table entries (these will be Debited for Payment, Credited for Receipt)
                foreach ($request->account_ids as $index => $accountId) {
                    $amount = $request->amounts[$index] ?? 0;
                    if ($amount > 0) {
                        if ($request->type == 'CP' || $request->type == 'BP') {
                            // Payment: Debit the expense/payable account
                            $voucherEntries[] = [
                                'account_id' => $accountId,
                                'debit' => $amount,
                                'credit' => 0,
                                'narration' => $request->narrations[$index] ?? null,
                            ];
                        } else {
                            // Receipt (CR, BR): Credit the income/receivable account
                            $voucherEntries[] = [
                                'account_id' => $accountId,
                                'debit' => 0,
                                'credit' => $amount,
                                'narration' => $request->narrations[$index] ?? null,
                            ];
                        }
                    }
                }

                // Add cash/bank account entry
                if ($request->type == 'CP' || $request->type == 'BP') {
                    // Payment: Credit the cash/bank account
                    $voucherEntries[] = [
                        'account_id' => $cashBankAccountId,
                        'debit' => 0,
                        'credit' => $totalAmount,
                        'narration' => 'By ' . ($request->type == 'CP' ? 'Cash' : 'Bank'),
                    ];
                } else {
                    // Receipt (CR, BR): Debit the cash/bank account
                    $voucherEntries[] = [
                        'account_id' => $cashBankAccountId,
                        'debit' => $totalAmount,
                        'credit' => 0,
                        'narration' => 'To ' . ($request->type == 'CR' ? 'Cash' : 'Bank'),
                    ];
                }
            }

            // Create Voucher
            $voucher = Voucher::create([
                'voucher_no' => $this->generateVoucherNo($request->type),
                'type' => $request->type,
                'date' => $request->date,
                'amount' => $isJournalOrContra ? array_sum($request->debits) : array_sum($request->amounts),
                'description' => $request->description,
                'reference_no' => $request->reference_no,
                'status' => 'approved',
                'created_by' => auth()->id(),
            ]);

            // Create Voucher Details
            foreach ($voucherEntries as $entry) {
                VoucherDetail::create([
                    'voucher_id' => $voucher->id,
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'narration' => $entry['narration'],
                ]);
            }

            // Update Chart of Account balances
            $this->updateAccountBalances($voucher);

            // ---------- INSERT STATEMENT LOGS ----------
            foreach ($voucher->details as $detail) {
                $this->insertStatementLog(
                    $detail->account_id,
                    $voucher->date,
                    $voucher->voucher_no,
                    $voucher->type,                     // e.g. CP, JV, etc.
                    $detail->debit,
                    $detail->credit,
                    $detail->narration ?? $voucher->description,
                    $voucher
                );
            }

            DB::commit();

            return redirect()->route('accounts.index', ['tab' => 'vouchers'])
                ->with('success', 'Voucher created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Update Account Balances - Dizi Cashier Logic
     */

    /**
     * Update Account Balances - Proper Accounting Logic
     * 
     * Accounting Rules:
     * - Asset Accounts: Debit = Increase, Credit = Decrease
     * - Expense Accounts: Debit = Increase, Credit = Decrease
     * - Liability Accounts: Debit = Decrease, Credit = Increase
     * - Equity Accounts: Debit = Decrease, Credit = Increase
     * - Income/Revenue Accounts: Debit = Decrease, Credit = Increase
     */
    private function updateAccountBalances($voucher)
    {
        foreach ($voucher->details as $detail) {
            $account = ChartOfAccount::find($detail->account_id);
            if ($account) {
                $oldBalance = $account->current_balance;

                // Apply proper accounting rules based on account type
                switch ($account->type) {
                    case 'asset':
                    case 'expense':
                        // For Asset & Expense: Debit increases, Credit decreases
                        $account->current_balance += $detail->debit;
                        $account->current_balance -= $detail->credit;
                        break;

                    case 'liability':
                    case 'equity':
                    case 'income':
                        // For Liability, Equity & Income: Debit decreases, Credit increases
                        $account->current_balance -= $detail->debit;
                        $account->current_balance += $detail->credit;
                        break;

                    default:
                        // Default case - treat like asset
                        $account->current_balance += $detail->debit;
                        $account->current_balance -= $detail->credit;
                        break;
                }

                $account->save();

                // Optional: Log the balance change for debugging
                \Log::info('Account balance updated', [
                    'voucher_id' => $voucher->id,
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'account_type' => $account->type,
                    'debit' => $detail->debit,
                    'credit' => $detail->credit,
                    'old_balance' => $oldBalance,
                    'new_balance' => $account->current_balance
                ]);
            }
        }
    }

    /**
     * Reverse Account Balances (for update/delete) - Opposite of updateAccountBalances
     */
    private function reverseAccountBalances($voucher)
    {
        foreach ($voucher->details as $detail) {
            $account = ChartOfAccount::find($detail->account_id);
            if ($account) {
                // Reverse the effect based on account type (opposite of update)
                switch ($account->type) {
                    case 'asset':
                    case 'expense':
                        // Reverse: Debit decreases, Credit increases
                        $account->current_balance -= $detail->debit;
                        $account->current_balance += $detail->credit;
                        break;

                    case 'liability':
                    case 'equity':
                    case 'income':
                        // Reverse: Debit increases, Credit decreases
                        $account->current_balance += $detail->debit;
                        $account->current_balance -= $detail->credit;
                        break;

                    default:
                        $account->current_balance -= $detail->debit;
                        $account->current_balance += $detail->credit;
                        break;
                }

                $account->save();
            }
        }
    }
    /**
     * Generate Voucher Number - Dizi Cashier Format
     */
    private function generateVoucherNo($type)
    {
        $prefixes = [
            'CP' => 'CP',
            'BP' => 'BP',
            'CR' => 'CR',
            'BR' => 'BR',
            'JV' => 'JV',
            'CV' => 'CV',
        ];

        $prefix = $prefixes[$type];
        $year = date('y');
        $month = date('m');

        $lastVoucher = Voucher::withTrashed()
            ->where('voucher_no', 'like', $prefix . '-' . $year . $month . '%')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastVoucher) {
            $lastNumber = intval(substr($lastVoucher->voucher_no, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . '-' . $year . $month . $newNumber;
    }


    public function edit($id)
    {
        $voucher = Voucher::with(['details.account'])->findOrFail($id);
        $costCenters = CostCenter::where('status', 'active')->get();

        // Get cash/bank account id for the voucher
        $cashBankDetail = $voucher->details->filter(function ($detail) {
            return $detail->account && $detail->account->type === 'asset' &&
                in_array($detail->account->sub_type, ['cash', 'bank', 'mobile_bank']);
        })->first();

        $cashBankAccountId = $cashBankDetail ? $cashBankDetail->account_id : null;

        return view('backend.accounts.vouchers.create_edit', compact('voucher', 'costCenters', 'cashBankAccountId'));
    }


    public function update(Request $request, $id)
    {
        $voucher = Voucher::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'type' => 'required|in:CP,BP,CR,BR,JV,CV',
            'account_ids' => 'required|array|min:1',
            'account_ids.*' => 'exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'reference_no' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $isJournalOrContra = in_array($request->type, ['JV', 'CV']);

        try {
            DB::beginTransaction();

            // Reverse old balances (also loads & caches the OLD details relation)
            $this->reverseAccountBalances($voucher);

            // Remove old statement logs for this voucher so they are not duplicated
            AccountStatementLog::where(function ($q) use ($voucher) {
                $q->where('source_type', Voucher::class)
                    ->where('source_id', $voucher->id);
            })->orWhere('voucher_id', $voucher->id)->delete();

            $voucherEntries = [];

            if ($isJournalOrContra) {
                // For JV and CV: Use debits[] and credits[] arrays
                $validator = Validator::make($request->all(), [
                    'debits' => 'required|array',
                    'credits' => 'required|array',
                ]);

                if ($validator->fails()) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $totalDebit = array_sum($request->debits);
                $totalCredit = array_sum($request->credits);

                if ($totalDebit != $totalCredit) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->with('error', 'Total debit must equal total credit')->withInput();
                }

                if ($totalDebit == 0) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->with('error', 'Amount cannot be zero')->withInput();
                }

                // Add table entries with debit/credit
                foreach ($request->account_ids as $index => $accountId) {
                    if (($request->debits[$index] ?? 0) > 0 || ($request->credits[$index] ?? 0) > 0) {
                        $voucherEntries[] = [
                            'account_id' => $accountId,
                            'debit' => $request->debits[$index] ?? 0,
                            'credit' => $request->credits[$index] ?? 0,
                            'narration' => $request->narrations[$index] ?? null,
                        ];
                    }
                }
            } else {
                // For CP, BP, CR, BR: Use amounts[] array (single amount column)
                $validator = Validator::make($request->all(), [
                    'amounts' => 'required|array',
                    'amounts.*' => 'numeric|min:0',
                    'cash_bank_account_id' => 'required|exists:chart_of_accounts,id',
                ]);

                if ($validator->fails()) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $totalAmount = array_sum($request->amounts);

                if ($totalAmount == 0) {
                    DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                    return redirect()->back()->with('error', 'Amount cannot be zero')->withInput();
                }

                $cashBankAccountId = $request->cash_bank_account_id;

                // Add table entries (these will be Debited for Payment, Credited for Receipt)
                foreach ($request->account_ids as $index => $accountId) {
                    $amount = $request->amounts[$index] ?? 0;
                    if ($amount > 0) {
                        if ($request->type == 'CP' || $request->type == 'BP') {
                            // Payment: Debit the expense/payable/equity account
                            $voucherEntries[] = [
                                'account_id' => $accountId,
                                'debit' => $amount,
                                'credit' => 0,
                                'narration' => $request->narrations[$index] ?? null,
                            ];
                        } else {
                            // Receipt (CR, BR): Credit the income/receivable account
                            $voucherEntries[] = [
                                'account_id' => $accountId,
                                'debit' => 0,
                                'credit' => $amount,
                                'narration' => $request->narrations[$index] ?? null,
                            ];
                        }
                    }
                }

                // Add cash/bank account entry
                if ($request->type == 'CP' || $request->type == 'BP') {
                    // Payment: Credit the cash/bank account
                    $voucherEntries[] = [
                        'account_id' => $cashBankAccountId,
                        'debit' => 0,
                        'credit' => $totalAmount,
                        'narration' => 'By ' . ($request->type == 'CP' ? 'Cash' : 'Bank'),
                    ];
                } else {
                    // Receipt (CR, BR): Debit the cash/bank account
                    $voucherEntries[] = [
                        'account_id' => $cashBankAccountId,
                        'debit' => $totalAmount,
                        'credit' => 0,
                        'narration' => 'To ' . ($request->type == 'CR' ? 'Cash' : 'Bank'),
                    ];
                }
            }

            // Update voucher
            $voucher->update([
                'date' => $request->date,
                'type' => $request->type,
                'amount' => $isJournalOrContra ? array_sum($request->debits) : array_sum($request->amounts),
                'description' => $request->description,
                'reference_no' => $request->reference_no,
            ]);

            // Delete old details
            VoucherDetail::where('voucher_id', $voucher->id)->delete();

            // Create new details
            foreach ($voucherEntries as $entry) {
                VoucherDetail::create([
                    'voucher_id' => $voucher->id,
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'],
                    'credit' => $entry['credit'],
                    'narration' => $entry['narration'],
                ]);
            }

            // IMPORTANT: refresh the details relation - the previously cached
            // relation still holds the OLD details, which would make both the
            // balance update and the statement logs use stale data
            $voucher->unsetRelation('details');

            // Update new balances
            $this->updateAccountBalances($voucher);
              // Insert NEW STATEMENT LOGS
            foreach ($voucher->details as $detail) {
                $this->insertStatementLog(
                    $detail->account_id,
                    $voucher->date,
                    $voucher->voucher_no,
                    $voucher->type,
                    $detail->debit,
                    $detail->credit,
                    $detail->narration ?? $voucher->description,
                    $voucher
                );
            }

            DB::commit();

            return redirect()->route('accounts.index', ['tab' => 'vouchers'])
                ->with('success', 'Voucher updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    // public function destroy($id)
    // {
    //     try {
    //         DB::beginTransaction();

    //         $voucher = Voucher::findOrFail($id);

    //         // Reverse balances
    //         $this->reverseAccountBalances($voucher);

    //         // Delete voucher
    //         $voucher->delete();

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Voucher deleted successfully'
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

     // ================== DELETE ==================
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $voucher = Voucher::lockForUpdate()->findOrFail($id); // document first (a repeated delete finds it gone)

            // Reverse balances
            $this->reverseAccountBalances($voucher);

            // Insert REVERSAL LOGS
            // Reversal entries use the ORIGINAL voucher date so that
            // period-based reports (P&L, expense/income summaries, cash book)
            // net to zero in the period where the voucher was originally booked
            $reversalDate = $voucher->date ? $voucher->date->toDateString() : now()->toDateString();
            foreach ($voucher->details as $detail) {
                $this->insertStatementLog(
                    $detail->account_id,
                    $reversalDate,
                    $voucher->voucher_no . '-DEL',
                    $voucher->type . '_deletion',
                    $detail->credit,    // reverse the original entry
                    $detail->debit,
                    'Deletion of voucher ' . $voucher->voucher_no,
                    $voucher
                );
            }

            $voucher->delete();

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Voucher deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function filter(Request $request)
    {
        $query = Voucher::with(['creator', 'details.account']);

        if ($request->voucher_no) {
            $query->where('voucher_no', 'like', "%{$request->voucher_no}%");
        }

        if ($request->voucher_type) {
            $query->where('type', $request->voucher_type);
        }

        if ($request->from_date && $request->to_date) {
            $query->whereBetween('date', [$request->from_date, $request->to_date]);
        }

        $vouchers = $query->latest()->paginate(20);

        if ($request->ajax()) {
            $html = view('backend.accounts.vouchers.partials.table_rows', compact('vouchers'))->render();
            return response()->json(['html' => $html]);
        }

        return view('backend.accounts.vouchers.index', compact('vouchers'));
    }

 public function print($id)
{
    $voucher = Voucher::with(['details.account', 'creator'])->findOrFail($id);
    
    // Check if PDF download requested
    if (request()->has('pdf')) {
        // Render the view to HTML
        $html = view('backend.accounts.vouchers.print', compact('voucher'))->render();
        
        // Initialize mPDF with proper settings for design preservation
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'default_font_size' => 10,
            'default_font' => 'dejavusans',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            // Important for preserving design
            'useSubstitutions' => false,
            'allow_output_buffering' => true,
            'css_styles' => true,
        ]);
        
        // Enable CSS support
        $mpdf->WriteHTML($html);
        
        // Output PDF for download
        return $mpdf->Output($voucher->voucher_no . '_' . date('Y-m-d') . '.pdf', 'D');
    }
    
    // For print view (browser print)
    return view('backend.accounts.vouchers.print', compact('voucher'));
}
}
