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
    
    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
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
            $transaction = BankTransaction::create([
                'transaction_no' => BankTransaction::generateTransactionNo('deposit'),
                'type' => 'deposit',
                'to_account_id' => $request->account_id,
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'reference' => $request->reference,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);
            
            $transaction->complete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Deposit processed successfully',
                'data' => $transaction
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error processing deposit: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function withdraw(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'description' => 'required',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $fromAccount = ChartOfAccount::find($request->account_id);
        
        if ($fromAccount->current_balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $transaction = BankTransaction::create([
                'transaction_no' => BankTransaction::generateTransactionNo('withdraw'),
                'type' => 'withdraw',
                'from_account_id' => $request->account_id,
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'reference' => $request->reference,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);
            
            $transaction->complete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Withdrawal processed successfully',
                'data' => $transaction
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error processing withdrawal: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_account_id' => 'required|exists:chart_of_accounts,id',
            'to_account_id' => 'required|exists:chart_of_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'description' => 'required',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $fromAccount = ChartOfAccount::find($request->from_account_id);
        
        if ($fromAccount->current_balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance in source account'
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $transaction = BankTransaction::create([
                'transaction_no' => BankTransaction::generateTransactionNo('transfer'),
                'type' => 'transfer',
                'from_account_id' => $request->from_account_id,
                'to_account_id' => $request->to_account_id,
                'amount' => $request->amount,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'reference' => $request->reference,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);
            
            $transaction->complete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'data' => $transaction
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error processing transfer: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function cashOut(Request $request)
    {
        return $this->withdraw($request);
    }
    
    public function chequeClearing(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'cheque_no' => 'required',
            'cheque_date' => 'required|date',
            'clearing_date' => 'required|date',
            'transaction_date' => 'required|date',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $transaction = BankTransaction::create([
                'transaction_no' => BankTransaction::generateTransactionNo('cheque_clearing'),
                'type' => 'cheque_clearing',
                'to_account_id' => $request->account_id,
                'amount' => $request->amount,
                'cheque_no' => $request->cheque_no,
                'cheque_date' => $request->cheque_date,
                'clearing_date' => $request->clearing_date,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'reference' => $request->reference,
                'created_by' => auth()->id(),
                'status' => 'cleared',
            ]);
            
            $transaction->complete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Cheque clearing processed successfully',
                'data' => $transaction
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error processing cheque: ' . $e->getMessage()
            ], 500);
        }
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