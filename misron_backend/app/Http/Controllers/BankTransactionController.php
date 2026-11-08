<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Voucher;
use App\Models\VoucherDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BankTransactionController extends Controller
{
    public function index()
    {
        $bankTransactions = BankTransaction::with(['fromAccount', 'toAccount', 'creator'])
            ->latest()
            ->take(50)
            ->get();
            
        $bankAccounts = ChartOfAccount::whereIn('sub_type', ['bank', 'mobile_bank', 'cash'])
            ->where('status', 'active')
            ->get();
            
        return view('backend.accounts.bank_operations', compact('bankTransactions', 'bankAccounts'));
    }
    
    public function getAccounts()
    {
        $accounts = ChartOfAccount::whereIn('sub_type', ['bank', 'mobile_bank', 'cash'])
            ->where('status', 'active')
            ->get(['id', 'name', 'current_balance']);
            
        return response()->json([
            'success' => true,
            'accounts' => $accounts
        ]);
    }
    
    /**
     * RETIRED (owner decision B-1.1): deposit, withdrawal, cash-out, transfer and cheque clearing on the bank screens are no longer supported.
     * Deposits, withdrawals and transfers are made on the balance-transfer screen (accounts.balance-transfers.*), which is unchanged.
     * Every request to a retired action is refused BEFORE any validation, lookup, transaction or write: nothing is posted, no number is taken,
     * no balance moves. Existing bank_transactions rows (history) stay readable through index() and show().
     */
    public function deposit(Request $request)
    {
        return $this->retired($request, 'deposit');
    }

    public function withdraw(Request $request)
    {
        return $this->retired($request, 'withdraw');
    }

    public function transfer(Request $request)
    {
        return $this->retired($request, 'transfer');
    }

    public function cashOut(Request $request)
    {
        return $this->retired($request, 'cash-out');
    }

    public function chequeClearing(Request $request)
    {
        return $this->retired($request, 'cheque-clearing');
    }

    private function retired(Request $request, string $action)
    {
        \Log::notice('BANK_ACTION_RETIRED: refused a request to the retired bank action "' . $action . '"; use the balance-transfer workflow.', ['user_id' => auth()->id()]);
        $message = 'This bank action has been retired. Use Balance Transfers for deposits, withdrawals and transfers.'; // plain text: translate() would insert a translation row, and a refusal must write nothing

        return response()->json([
            'success' => false,
            'retired' => true,
            'action' => $action,
            'message' => $message,
            'use' => route('accounts.balance-transfers.create'),
        ], 410);
    }

    public function show($id)
    {
        $transaction = BankTransaction::with(['fromAccount', 'toAccount', 'creator', 'voucher'])
            ->findOrFail($id);
            
        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }
    
    public function export()
    {
        // Implement export functionality
        return response()->json([
            'success' => true,
            'message' => 'Export feature coming soon'
        ]);
    }
}