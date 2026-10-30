<?php

namespace App\Http\Controllers;

use App\Models\CustomerPayment;
use App\Models\Order;
use App\Models\OrderPaymentMapping;
use App\Models\ChartOfAccount;
use App\Models\TransactionMethod;
use App\Models\Voucher;
use App\Models\VoucherDetail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    use \App\Traits\StatementLogTrait;
    public function index()
    {
        $customerPayments = CustomerPayment::with(['customer', 'paymentAccount', 'transactionMethod', 'orders'])
            ->latest()
            ->paginate(20);
            
        // Get customers who have placed orders (users)
        $customers = User::whereHas('orders', function($q) {
            $q->where('payment_status', 'paid');
        })->get();
        
        $paymentAccounts = ChartOfAccount::whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->get();
            
        $transactionMethods = TransactionMethod::where('status', 'active')->get();
        
        // For AJAX response
        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'payments' => $customerPayments
            ]);
        }
        
        return view('backend.accounts.customer_payments', compact(
            'customerPayments', 'customers', 'paymentAccounts', 'transactionMethods'
        ));
    }
    
    /**
     * Get paid orders for a specific customer that haven't been fully paid yet
     */
    public function getPaidOrders($customerId)
    {
        $orders = Order::where('user_id', $customerId)
            ->where('payment_status', 'paid')
            ->with(['customerPayments' => function($q) {
                $q->withPivot('amount_applied');
            }])
            ->get();
        
        // Calculate remaining amount for each order
        $ordersWithRemaining = $orders->map(function($order) {
            $paidAmount = $order->customerPayments->sum('pivot.amount_applied');
            $remainingAmount = $order->grand_total - $paidAmount;
            
            return [
                'id' => $order->id,
                'order_code' => $order->code,
                'grand_total' => $order->grand_total,
                'paid_amount' => $paidAmount,
                'remaining_amount' => max(0, $remainingAmount),
                'is_fully_paid' => $remainingAmount <= 0
            ];
        })->filter(function($order) {
            // Only show orders that are not fully paid
            return !$order['is_fully_paid'];
        });
        
        return response()->json([
            'success' => true,
            'orders' => $ordersWithRemaining,
            'customer_id' => $customerId
        ]);
    }
    
    /**
     * Store customer payment from paid orders
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'exists:orders,id',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
            'transaction_method_id' => 'required|exists:transaction_methods,id',
            'payment_date' => 'required|date',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        DB::beginTransaction();
        
        try {
            $customer = User::findOrFail($request->customer_id);
            
            // Get the selected orders with their remaining amounts
            $orders = Order::whereIn('id', $request->order_ids)
                ->where('user_id', $customer->id)
                ->where('payment_status', 'paid')
                ->with(['customerPayments' => function($q) {
                    $q->withPivot('amount_applied');
                }])
                ->get();
            
            if ($orders->isEmpty()) {
                throw new \Exception('No valid paid orders found for this customer');
            }
            
            // Calculate total remaining amount for selected orders
            $totalRemaining = 0;
            $orderRemainingMap = [];
            
            foreach ($orders as $order) {
                $paidAmount = $order->customerPayments->sum('pivot.amount_applied');
                $remaining = $order->grand_total - $paidAmount;
                $orderRemainingMap[$order->id] = $remaining;
                $totalRemaining += $remaining;
            }
            
            // Check if payment amount is valid
            if ($request->amount > $totalRemaining) {
                throw new \Exception('Payment amount (' . $request->amount . ') exceeds total remaining amount (' . $totalRemaining . ')');
            }
            
            // Create customer payment record
            $payment = CustomerPayment::create([
                'customer_id' => $request->customer_id,
                'payment_no' => CustomerPayment::generatePaymentNo(),
                'amount' => $request->amount,
                'received_amount' => $request->amount,
                'due_amount' => $totalRemaining - $request->amount,
                'payment_account_id' => $request->payment_account_id,
                'transaction_method_id' => $request->transaction_method_id,
                'payment_type' => $request->amount >= $totalRemaining ? 'full' : 'partial',
                'payment_date' => $request->payment_date,
                'notes' => $request->notes,
                'reference_no' => $request->reference_no,
                'created_by' => auth()->id(),
                'status' => 'pending',
            ]);
            
            // Apply payment to orders
            $remainingAmount = $request->amount;
            foreach ($orders as $order) {
                if ($remainingAmount <= 0) break;
                
                $remainingOrderAmount = $orderRemainingMap[$order->id];
                $applyAmount = min($remainingOrderAmount, $remainingAmount);
                
                OrderPaymentMapping::create([
                    'order_id' => $order->id,
                    'customer_payment_id' => $payment->id,
                    'amount_applied' => $applyAmount,
                ]);
                
                $remainingAmount -= $applyAmount;
                
                // Update order payment status if fully paid
                $newPaidAmount = $order->customerPayments()->sum('order_payment_mappings.amount_applied') + $applyAmount;
                if ($newPaidAmount >= $order->grand_total) {
                    $order->update(['payment_status' => 'completed']);
                }
            }
            
            // Determine voucher type based on payment account
            $paymentAccount = ChartOfAccount::find($request->payment_account_id);
            $voucherType = ($paymentAccount && $paymentAccount->sub_type === 'cash') ? 'CR' : 'BR';

            // Create voucher for receipt
            $voucher = Voucher::create([
                'voucher_no' => Voucher::generateVoucherNo($voucherType),
                'type' => $voucherType,
                'date' => $payment->payment_date,
                'amount' => $payment->amount,
                'description' => "Payment received from customer: {$customer->name} for orders: " . implode(', ', $request->order_ids),
                'status' => 'approved',
                'created_by' => auth()->id(),
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            
            // Debit Payment Account (Asset increase)
            VoucherDetail::create([
                'voucher_id' => $voucher->id,
                'account_id' => $payment->payment_account_id,
                'debit' => $payment->amount,
                'credit' => 0,
                'narration' => "Payment received from customer: {$customer->name} via " . ($payment->transactionMethod->name ?? ''),
                'customer_id' => $payment->customer_id,
            ]);
            
            // Credit Accounts Receivable
            $receivableAccount = ChartOfAccount::where('sub_type', 'receivable')->first();
            if (!$receivableAccount) {
                $receivableAccount = ChartOfAccount::create([
                    'code' => '1-1301',
                    'name' => 'Accounts Receivable',
                    'type' => 'asset',
                    'sub_type' => 'receivable',
                    'current_balance' => 0,
                    'status' => 'active',
                ]);
            }
            
            VoucherDetail::create([
                'voucher_id' => $voucher->id,
                'account_id' => $receivableAccount->id,
                'debit' => 0,
                'credit' => $payment->amount,
                'narration' => "Payment received from customer: {$customer->name}",
                'customer_id' => $payment->customer_id,
            ]);
            
            $payment->voucher_id = $voucher->id;
            $payment->status = 'received';
            $payment->save();
            
            // Post voucher to accounts.
            // NOTE: postToAccounts() updates the chart-of-accounts balances AND
            // creates the statement logs (one per voucher detail). No additional
            // insertStatementLog() calls must be made here, otherwise every
            // customer payment would be logged TWICE in account_statement_logs.
            $voucher->postToAccounts();

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Customer payment processed successfully',
                'data' => $payment
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get customer's total due amount
     */
    public function getCustomerDue($customerId)
    {
        $orders = Order::where('user_id', $customerId)
            ->where('payment_status', 'paid')
            ->with(['customerPayments' => function($q) {
                $q->withPivot('amount_applied');
            }])
            ->get();
        
        $totalAmount = 0;
        $totalPaid = 0;
        
        foreach ($orders as $order) {
            $totalAmount += $order->grand_total;
            $totalPaid += $order->customerPayments->sum('pivot.amount_applied');
        }
        
        $dueAmount = $totalAmount - $totalPaid;
        
        return response()->json([
            'success' => true,
            'due_amount' => max(0, $dueAmount),
            'total_amount' => $totalAmount,
            'paid_amount' => $totalPaid,
        ]);
    }
    
    /**
     * Show payment details
     */
    public function show($id)
    {
        $payment = CustomerPayment::with(['customer', 'paymentAccount', 'transactionMethod', 'orders', 'voucher', 'creator'])
            ->findOrFail($id);
            
        return response()->json([
            'success' => true,
            'data' => $payment
        ]);
    }
    
    /**
     * Delete payment
     */
    public function destroy($id)
    {
        $payment = CustomerPayment::findOrFail($id);
        
        DB::beginTransaction();
        
        try {
            // Reverse order payment statuses
            foreach ($payment->orders as $order) {
                $newPaidAmount = $order->customerPayments()
                    ->where('customer_payment_id', '!=', $payment->id)
                    ->sum('order_payment_mappings.amount_applied');
                
                if ($newPaidAmount < $order->grand_total) {
                    $order->update(['payment_status' => 'paid']);
                }
            }
            
            // Reverse voucher account balances
            if ($payment->voucher) {
                $payment->voucher->reverseFromAccounts();
                
                // Insert reversal statement logs
                // Use the ORIGINAL payment date so period reports net to zero
                $reversalDate = $payment->payment_date ? date('Y-m-d', strtotime($payment->payment_date)) : now()->toDateString();
                foreach ($payment->voucher->details as $detail) {
                    $this->insertStatementLog(
                        $detail->account_id,
                        $reversalDate,
                        $payment->payment_no . '-DEL',
                        'customer_payment_deletion',
                        $detail->credit,
                        $detail->debit,
                        'Deletion of payment ' . $payment->payment_no,
                        $payment
                    );
                }
                
                $payment->voucher()->delete();
            }
            
            // Delete mappings
            $payment->orderMappings()->delete();
            
            // Delete payment
            $payment->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting payment: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Auto-import payments from paid orders (can be run via cron)
     */
    public function autoImport(Request $request)
    {
        // Get customers with paid orders that haven't been fully paid
        $customers = User::whereHas('orders', function($q) {
            $q->where('payment_status', 'paid');
        })->get();
        
        $imported = 0;
        $errors = [];
        
        foreach ($customers as $customer) {
            // Get unpaid paid orders for this customer
            $orders = Order::where('user_id', $customer->id)
                ->where('payment_status', 'paid')
                ->with(['customerPayments' => function($q) {
                    $q->withPivot('amount_applied');
                }])
                ->get();
            
            $unpaidOrders = [];
            $totalDue = 0;
            
            foreach ($orders as $order) {
                $paidAmount = $order->customerPayments->sum('pivot.amount_applied');
                if ($paidAmount < $order->grand_total) {
                    $unpaidOrders[] = $order->id;
                    $totalDue += ($order->grand_total - $paidAmount);
                }
            }
            
            if (!empty($unpaidOrders) && $totalDue > 0) {
                try {
                    // Get default accounts
                    $defaultAccount = ChartOfAccount::whereIn('sub_type', ['cash', 'bank'])->first();
                    $defaultMethod = TransactionMethod::where('status', 'active')->first();
                    
                    if ($defaultAccount && $defaultMethod) {
                        // Create a manual request to process payment
                        $paymentRequest = new Request([
                            'customer_id' => $customer->id,
                            'amount' => $totalDue,
                            'order_ids' => $unpaidOrders,
                            'payment_account_id' => $defaultAccount->id,
                            'transaction_method_id' => $defaultMethod->id,
                            'payment_date' => now()->format('Y-m-d'),
                            'notes' => 'Auto-imported payment',
                        ]);
                        
                        $response = $this->store($paymentRequest);
                        $result = json_decode($response->getContent(), true);
                        
                        if ($result['success']) {
                            $imported++;
                        } else {
                            $errors[] = "Customer {$customer->name}: " . $result['message'];
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Customer {$customer->name}: " . $e->getMessage();
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => "Imported {$imported} payments",
            'errors' => $errors
        ]);
    }
}