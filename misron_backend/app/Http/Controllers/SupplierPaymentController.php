<?php

namespace App\Http\Controllers;

use App\Models\AccountStatementLog;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\ChartOfAccount;
use App\Models\Purchase;
use App\Traits\StatementLogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SupplierPaymentController extends Controller
{
    public function __construct()
    {
        // Authentication is on the route group; these are the per-action role permissions.
        $this->middleware(['permission:view_supplier_payments'])->only(['index', 'show', 'getSupplierDue', 'getAccountsBySubType']);
        $this->middleware(['permission:make_supplier_payment'])->only(['create', 'store', 'edit', 'update', 'destroy']);
    }

    use StatementLogTrait;

    public function index(Request $request)
    {
        $query = SupplierPayment::with(['supplier', 'paymentAccount']);

        if ($request->supplier) {
            $query->whereHas('supplier', fn($q) => $q->where('business_name', 'like', "%{$request->supplier}%"));
        }
        if ($request->payment_no) {
            $query->where('payment_no', 'like', "%{$request->payment_no}%");
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_range) {
            $dates = explode(' to ', $request->date_range);
            if (count($dates) == 2) {
                $query->whereBetween('payment_date', [$dates[0], $dates[1]]);
            }
        }
        if ($request->to_date) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }

        $supplierPayments = $query->orderBy('id', 'desc')->paginate(15);

        if ($request->ajax()) {
            $tableHtml = view('backend.accounts.supplier-payments.partials.table_rows', compact('supplierPayments'))->render();
            $mobileHtml = view('backend.accounts.supplier-payments.partials.mobile_cards', compact('supplierPayments'))->render();
            return response()->json([
                'table_html' => $tableHtml,
                'mobile_html' => $mobileHtml,
                'total_count' => $supplierPayments->total(),
                'pagination' => $supplierPayments->links()->toHtml(),
            ]);
        }

        $suppliers = Supplier::orderBy('business_name')->get();
        return view('backend.accounts.supplier-payments.index', compact('supplierPayments', 'suppliers'));
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('business_name')->get();
        return view('backend.accounts.supplier-payments.create-edit', compact('suppliers'));
    }


    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'supplier_id'     => 'required|exists:suppliers,id',
    //         'payment_date'    => 'required|date',
    //         'amount'          => 'required|numeric|min:0.01',
    //         'payment_method'  => 'required|in:CASH,BANK,MBANKING',
    //         'account_head_id' => 'required|exists:chart_of_accounts,id',
    //         'transaction_no'  => 'required_if:payment_method,MBANKING',
    //         'paid_from'       => 'nullable|string',
    //         'notes'           => 'nullable|string',
    //         'attachments.*'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
    //         'allocations'     => 'required|json',
    //     ]);

    //     DB::beginTransaction();
    //     try {
    //         $supplier = Supplier::findOrFail($request->supplier_id);
    //         $amount = $request->amount;
    //         $allocations = json_decode($request->allocations, true);

    //         $totalAllocated = array_sum(array_column($allocations, 'paid_amount'));
    //         if (abs($totalAllocated - $amount) > 0.01) {
    //             throw new \Exception('Allocated amounts do not match total payment.');
    //         }

    //         foreach ($allocations as $alloc) {
    //             $purchase = Purchase::find($alloc['purchase_id']);
    //             if (!$purchase) throw new \Exception('Invalid purchase');
    //             if ($alloc['paid_amount'] > $purchase->total_payment_due) {
    //                 throw new \Exception("Amount exceeds due for invoice {$purchase->reference_no}");
    //             }
    //         }

    //         $attachments = [];
    //         if ($request->hasFile('attachments')) {
    //             foreach ($request->file('attachments') as $file) {
    //                 $attachments[] = $file->store('supplier-payments', 'public');
    //             }
    //         }

    //         $lastPayment = SupplierPayment::orderBy('id', 'desc')->first();
    //         $nextNumber = $lastPayment ? intval(substr($lastPayment->payment_no, 4)) + 1 : 1;
    //         $paymentNo = 'PAY-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

    //         $newDue = $supplier->due_amount - $amount;
    //         $status = $newDue <= 0 ? 'paid' : 'partial';

    //         $payment = SupplierPayment::create([
    //             'supplier_id'        => $request->supplier_id,
    //             'payment_no'         => $paymentNo,
    //             'amount'             => $amount,
    //             'paid_amount'        => $amount,
    //             'due_amount'         => $newDue,
    //             'payment_account_id' => $request->account_head_id,
    //             'payment_method'     => $request->payment_method,
    //             'payment_date'       => $request->payment_date,
    //             'notes'              => $request->notes,
    //             'reference_no'       => $request->reference_no,
    //             'transaction_no'     => $request->transaction_no,
    //             'paid_from'          => $request->paid_from,
    //             'attachments'        => json_encode($attachments),
    //             'status'             => $status,
    //             'created_by'         => auth()->id(),
    //         ]);

    //         foreach ($allocations as $alloc) {
    //             $purchase = Purchase::find($alloc['purchase_id']);
    //             $purchase->total_payment_due -= $alloc['paid_amount'];
    //             $purchase->save();
    //             $payment->purchases()->attach($purchase->id, ['paid_amount' => $alloc['paid_amount']]);
    //         }

    //         $supplier->due_amount = $newDue;
    //         $supplier->paid_amount += $amount;
    //         $supplier->save();

    //         // Accounting adjustments
    //         $payable = ChartOfAccount::getAccountsPayable();
    //         if (!$payable) throw new \Exception('Accounts Payable not found');
    //         $payable->current_balance -= $amount;
    //         $payable->save();

    //         $paymentAccount = ChartOfAccount::find($request->account_head_id);
    //         if (!$paymentAccount) throw new \Exception('Payment account not found');
    //         if ($paymentAccount->current_balance < $amount) {
    //             throw new \Exception('Insufficient balance in selected account');
    //         }
    //         $paymentAccount->current_balance -= $amount;
    //         $paymentAccount->save();

    //         // ---------- STATEMENT LOGS ----------
    //         // 1. Accounts Payable – debit (reduce liability)
    //         $this->insertStatementLog(
    //             $payable->id,
    //             $request->payment_date,
    //             $paymentNo,
    //             'supplier_payment',
    //             $amount,             // debit
    //             0,                   // credit
    //             $request->notes ?? 'Payment to ' . $supplier->business_name,
    //             $payment
    //         );

    //         // 2. Payment account (cash/bank) – credit (reduce asset)
    //         $this->insertStatementLog(
    //             $request->account_head_id,
    //             $request->payment_date,
    //             $paymentNo,
    //             'supplier_payment',
    //             0,
    //             $amount,
    //             $request->notes ?? 'Payment to ' . $supplier->business_name,
    //             $payment
    //         );

    //         DB::commit();
    //         return response()->json(['success' => true, 'message' => 'Payment recorded successfully']);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    //     }
    // }

public function store(Request $request)
{
    // Manual validation to ensure proper AJAX response
    $validator = Validator::make($request->all(), [
        'supplier_id'     => 'required|exists:suppliers,id',
        'payment_date'    => 'required|date',
        'amount'          => 'required|numeric|min:0.01',
        'payment_method'  => 'required|in:CASH,BANK,MBANKING',
        'account_head_id' => 'required|exists:chart_of_accounts,id',
        'transaction_no'  => 'required_if:payment_method,MBANKING',
        'paid_from'       => 'nullable|string',
        'notes'           => 'nullable|string',
        'attachments.*'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        'allocations'     => 'required',
    ]);

    // Return validation errors as JSON
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()
        ], 422);
    }

    // Decode allocations
    $allocations = json_decode($request->allocations, true);
    
    // Validate allocations structure
    if (empty($allocations) || !is_array($allocations)) {
        return response()->json([
            'success' => false,
            'message' => 'Please select at least one invoice to allocate payment.'
        ], 422);
    }

    // Validate allocation data structure
    foreach ($allocations as $alloc) {
        if (!isset($alloc['purchase_id']) || !isset($alloc['paid_amount'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid allocation data format.'
            ], 422);
        }
        if ($alloc['paid_amount'] <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount must be greater than zero.'
            ], 422);
        }
    }

    DB::beginTransaction();
    try {
        $supplier = Supplier::lockForUpdate()->findOrFail($request->supplier_id);
        $amount = (float) $request->amount;

        // Calculate total allocated amount
        $totalAllocated = array_sum(array_column($allocations, 'paid_amount'));

        // Validate total allocation matches payment amount
        if (abs($totalAllocated - $amount) > 0.01) {
            DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
            return response()->json([
                'success' => false,
                'message' => 'Allocated amounts do not match total payment amount.'
            ], 422);
        }

        // Validate each purchase exists and has sufficient due
        foreach ($allocations as $alloc) {
            $purchase = Purchase::find($alloc['purchase_id']);
            if (!$purchase) {
                throw new \Exception('Invalid purchase reference in allocations');
            }
            if ($alloc['paid_amount'] > $purchase->total_payment_due) {
                throw new \Exception("Payment amount exceeds due amount for invoice {$purchase->reference_no}");
            }
        }

        // Handle file attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            // once per request (a deadlock retry must not store them again); removed if the request finally fails
            $attachments = \App\Support\RequestSideEffects::once($request, 'payment_attachments', function () use ($request) {
                $stored = [];
                foreach ($request->file('attachments') as $file) {
                    $stored[] = $file->store('supplier-payments', 'public');
                }

                return $stored;
            }, function ($paths) {
                foreach ($paths as $path) {
                    Storage::disk('public')->delete($path);
                }
            });
        }

        // Generate payment number
        // Calculate new supplier due
        $newDue = $supplier->due_amount - $amount;
        $status = $newDue <= 0 ? 'paid' : 'partial';

        // Create payment record
        $payment = SupplierPayment::createNumbered([
            'supplier_id'        => $request->supplier_id,
            'amount'             => $amount,
            'paid_amount'        => $amount,
            'due_amount'         => $newDue,
            'payment_account_id' => $request->account_head_id,
            'payment_method'     => $request->payment_method,
            'payment_date'       => $request->payment_date,
            'notes'              => $request->notes,
            'reference_no'       => $request->reference_no,
            'transaction_no'     => $request->transaction_no,
            'paid_from'          => $request->paid_from,
            'attachments'        => json_encode($attachments),
            'status'             => $status,
            'created_by'         => auth()->id(),
        ]);
        $paymentNo = $payment->payment_no;

        // Process each allocation
        foreach ($allocations as $alloc) {
            $purchase = Purchase::find($alloc['purchase_id']);
            
            // Update purchase due amount
            $purchase->total_payment_due -= $alloc['paid_amount'];
            $purchase->save();
            
            // Create pivot table record
            $payment->purchases()->attach($purchase->id, [
                'paid_amount' => $alloc['paid_amount'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Update supplier balances
        $supplier->due_amount = $newDue;
        $supplier->paid_amount += $amount;
        $supplier->save();

        // Accounting entries
        $payable = ChartOfAccount::getAccountsPayable();
        if (!$payable) {
            throw new \Exception('Accounts Payable account not found');
        }
        $payable->current_balance -= $amount;
        $payable->save();

        $paymentAccount = ChartOfAccount::lockForUpdate()->find($request->account_head_id); // locked: the balance check below must see a concurrent payment
        if (!$paymentAccount) {
            throw new \Exception('Payment account not found');
        }
        if ($paymentAccount->current_balance < $amount) {
            throw new \Exception('Insufficient balance in selected account');
        }
        $paymentAccount->current_balance -= $amount;
        $paymentAccount->save();

        // Statement logs
        $this->insertStatementLog(
            $payable->id,
            $request->payment_date,
            $paymentNo,
            'supplier_payment',
            $amount,
            0,
            $request->notes ?? 'Payment to ' . $supplier->business_name,
            $payment
        );

        $this->insertStatementLog(
            $request->account_head_id,
            $request->payment_date,
            $paymentNo,
            'supplier_payment',
            0,
            $amount,
            $request->notes ?? 'Payment to ' . $supplier->business_name,
            $payment
        );

        DB::commit();
        
        return response()->json([
            'success' => true, 
            'message' => 'Payment recorded successfully',
            'redirect' => route('accounts.supplier-payments.index')
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'success' => false, 
            'message' => $e->getMessage()
        ], 500);
    }
}



    public function edit($id)
    {
        $payment = SupplierPayment::findOrFail($id);
        $suppliers = Supplier::orderBy('business_name')->get();
        return view('backend.accounts.supplier-payments.create-edit', compact('payment', 'suppliers'));
    }

    // public function update(Request $request, $id)
    // {
    //     $request->validate([
    //         'payment_date'    => 'required|date',
    //         'amount'          => 'required|numeric|min:0.01',
    //         'payment_method'  => 'required|in:CASH,BANK,MBANKING',
    //         'account_head_id' => 'required|exists:chart_of_accounts,id',
    //         'transaction_no'  => 'required_if:payment_method,MBANKING',
    //         'paid_from'       => 'nullable|string',
    //         'notes'           => 'nullable|string',
    //         'attachments.*'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
    //         'allocations'     => 'required|json',
    //     ]);

    //     DB::beginTransaction();
    //     try {
    //         $payment = SupplierPayment::findOrFail($id);
    //         $oldSupplier = Supplier::findOrFail($payment->supplier_id);
    //         $newSupplier = Supplier::findOrFail($request->supplier_id);

    //         $newAmount = $request->amount;
    //         $newAllocations = json_decode($request->allocations, true);
    //         $totalAllocated = array_sum(array_column($newAllocations, 'paid_amount'));
    //         if (abs($totalAllocated - $newAmount) > 0.01) {
    //             throw new \Exception('Allocated amounts do not match total payment.');
    //         }

    //         // ----- REVERT OLD -----
    //         // Revert purchase dues
    //         foreach ($payment->purchases as $oldPurchase) {
    //             $oldPaid = $oldPurchase->pivot->paid_amount;
    //             $oldPurchase->total_payment_due += $oldPaid;
    //             $oldPurchase->save();
    //         }
    //         // Revert supplier balance
    //         $oldSupplier->due_amount += $payment->amount;
    //         $oldSupplier->paid_amount -= $payment->amount;
    //         $oldSupplier->save();
    //         // Revert accounting
    //         $payable = ChartOfAccount::getAccountsPayable();
    //         if ($payable) {
    //             $payable->current_balance += $payment->amount;
    //             $payable->save();
    //         }
    //         $oldAccount = ChartOfAccount::find($payment->payment_account_id);
    //         if ($oldAccount) {
    //             $oldAccount->current_balance += $payment->amount;
    //             $oldAccount->save();
    //         }
    //         // Detach old purchases
    //         $payment->purchases()->detach();

    //         // ----- APPLY NEW -----
    //         // Validate new allocations
    //         foreach ($newAllocations as $alloc) {
    //             $purchase = Purchase::find($alloc['purchase_id']);
    //             if (!$purchase) throw new \Exception('Invalid purchase');
    //             if ($alloc['paid_amount'] > $purchase->total_payment_due) {
    //                 throw new \Exception("Amount exceeds due for invoice {$purchase->reference_no}");
    //             }
    //         }

    //         // Handle attachments
    //         if ($request->hasFile('attachments')) {
    //             // Delete old attachments
    //             $oldAtts = json_decode($payment->attachments ?? '[]', true);
    //             foreach ($oldAtts as $file) Storage::disk('public')->delete($file);
    //             $newAtts = [];
    //             foreach ($request->file('attachments') as $file) {
    //                 $newAtts[] = $file->store('supplier-payments', 'public');
    //             }
    //             $payment->attachments = json_encode($newAtts);
    //         }

    //         $newSupplierDue = $newSupplier->due_amount - $newAmount;
    //         $status = $newSupplierDue <= 0 ? 'paid' : 'partial';

    //         $payment->payment_date = $request->payment_date;
    //         $payment->amount = $newAmount;
    //         $payment->paid_amount = $newAmount;
    //         $payment->due_amount = $newSupplierDue;
    //         $payment->payment_account_id = $request->account_head_id;
    //         $payment->payment_method = $request->payment_method;
    //         $payment->notes = $request->notes;
    //         $payment->reference_no = $request->reference_no;
    //         $payment->transaction_no = $request->transaction_no;
    //         $payment->paid_from = $request->paid_from;
    //         $payment->status = $status;
    //         $payment->save();

    //         // Update purchases and pivot
    //         foreach ($newAllocations as $alloc) {
    //             $purchase = Purchase::find($alloc['purchase_id']);
    //             $purchase->total_payment_due -= $alloc['paid_amount'];
    //             $purchase->save();
    //             $payment->purchases()->attach($purchase->id, ['paid_amount' => $alloc['paid_amount']]);
    //         }

    //         // Update new supplier balance
    //         $newSupplier->due_amount = $newSupplierDue;
    //         $newSupplier->paid_amount += $newAmount;
    //         $newSupplier->save();

    //         // Accounting: Debit Accounts Payable
    //         $payable->current_balance -= $newAmount;
    //         $payable->save();
    //         // Credit new payment account
    //         $newAccount = ChartOfAccount::find($request->account_head_id);
    //         if (!$newAccount) throw new \Exception('Payment account not found');
    //         if ($newAccount->current_balance < $newAmount) {
    //             throw new \Exception('Insufficient balance in selected account');
    //         }
    //         $newAccount->current_balance -= $newAmount;
    //         $newAccount->save();

    //         DB::commit();
    //         return response()->json(['success' => true, 'message' => 'Payment updated successfully']);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    //     }
    // }
    // ================== UPDATE ==================
    public function update(Request $request, $id)
    {
        $request->validate([
            'payment_date'    => 'required|date',
            'amount'          => 'required|numeric|min:0.01',
            'payment_method'  => 'required|in:CASH,BANK,MBANKING',
            'account_head_id' => 'required|exists:chart_of_accounts,id',
            'transaction_no'  => 'required_if:payment_method,MBANKING',
            'paid_from'       => 'nullable|string',
            'notes'           => 'nullable|string',
            'attachments.*'   => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'allocations'     => 'required|json',
        ]);

        DB::beginTransaction();
        try {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($id); // document first: re-read under its lock
            $oldSupplier = Supplier::lockForUpdate()->findOrFail($payment->supplier_id);
            $newSupplier = Supplier::lockForUpdate()->findOrFail($request->supplier_id);

            $newAmount = $request->amount;
            $newAllocations = json_decode($request->allocations, true);
            $totalAllocated = array_sum(array_column($newAllocations, 'paid_amount'));
            if (abs($totalAllocated - $newAmount) > 0.01) {
                throw new \Exception('Allocated amounts do not match total payment.');
            }

            // ---- REVERT OLD ----
            foreach ($payment->purchases as $oldPurchase) {
                $oldPaid = $oldPurchase->pivot->paid_amount;
                $oldPurchase->total_payment_due += $oldPaid;
                $oldPurchase->save();
            }
            $oldSupplier->due_amount += $payment->amount;
            $oldSupplier->paid_amount -= $payment->amount;
            $oldSupplier->save();

            $payable = ChartOfAccount::getAccountsPayable();
            if ($payable) {
                $payable->current_balance += $payment->amount;
                $payable->save();
            }
            $oldAccount = ChartOfAccount::find($payment->payment_account_id);
            if ($oldAccount) {
                $oldAccount->current_balance += $payment->amount;
                $oldAccount->save();
            }
            $payment->purchases()->detach();

            // --- Insert REVERSAL LOGS ---
            // Use the ORIGINAL payment date so period reports net to zero
            $reversalDate = date('Y-m-d', strtotime($payment->payment_date));
            if ($payable) {
                $this->insertStatementLog(
                    $payable->id,
                    $reversalDate,
                    $payment->payment_no . '-REV',
                    'supplier_payment_reversal',
                    0,                   // credit to reverse original debit
                    $payment->amount,
                    'Reversal for edit of ' . $payment->payment_no,
                    $payment
                );
            }
            if ($oldAccount) {
                $this->insertStatementLog(
                    $oldAccount->id,
                    $reversalDate,
                    $payment->payment_no . '-REV',
                    'supplier_payment_reversal',
                    $payment->amount,    // debit to reverse original credit
                    0,
                    'Reversal for edit of ' . $payment->payment_no,
                    $payment
                );
            }

            // ---- APPLY NEW ----
            foreach ($newAllocations as $alloc) {
                $purchase = Purchase::find($alloc['purchase_id']);
                if (!$purchase) throw new \Exception('Invalid purchase');
                if ($alloc['paid_amount'] > $purchase->total_payment_due) {
                    throw new \Exception("Amount exceeds due for invoice {$purchase->reference_no}");
                }
            }

            if ($request->hasFile('attachments')) {
                $oldAtts = json_decode($payment->attachments ?? '[]', true);
                $newAtts = \App\Support\RequestSideEffects::once($request, 'payment_attachments', function () use ($request) {
                    $stored = [];
                    foreach ($request->file('attachments') as $file) {
                        $stored[] = $file->store('supplier-payments', 'public');
                    }

                    return $stored;
                }, function ($paths) {
                    foreach ($paths as $path) {
                        Storage::disk('public')->delete($path);
                    }
                });
                $payment->attachments = json_encode($newAtts);
                // old files are removed only after the update is committed
                DB::afterCommit(function () use ($oldAtts) {
                    foreach ((array) $oldAtts as $file) {
                        Storage::disk('public')->delete($file);
                    }
                });
            }

            $newSupplierDue = $newSupplier->due_amount - $newAmount;
            $status = $newSupplierDue <= 0 ? 'paid' : 'partial';

            $payment->payment_date = $request->payment_date;
            $payment->amount = $newAmount;
            $payment->paid_amount = $newAmount;
            $payment->due_amount = $newSupplierDue;
            $payment->payment_account_id = $request->account_head_id;
            $payment->payment_method = $request->payment_method;
            $payment->notes = $request->notes;
            $payment->reference_no = $request->reference_no;
            $payment->transaction_no = $request->transaction_no;
            $payment->paid_from = $request->paid_from;
            $payment->status = $status;
            $payment->save();

            foreach ($newAllocations as $alloc) {
                $purchase = Purchase::find($alloc['purchase_id']);
                $purchase->total_payment_due -= $alloc['paid_amount'];
                $purchase->save();
                $payment->purchases()->attach($purchase->id, ['paid_amount' => $alloc['paid_amount']]);
            }

            $newSupplier->due_amount = $newSupplierDue;
            $newSupplier->paid_amount += $newAmount;
            $newSupplier->save();

            $payable->current_balance -= $newAmount;
            $payable->save();

            $newAccount = ChartOfAccount::lockForUpdate()->find($request->account_head_id); // locked: the balance check below must see a concurrent payment
            if (!$newAccount) throw new \Exception('Payment account not found');
            if ($newAccount->current_balance < $newAmount) {
                throw new \Exception('Insufficient balance in selected account');
            }
            $newAccount->current_balance -= $newAmount;
            $newAccount->save();

            // --- NEW STATEMENT LOGS ---
            $this->insertStatementLog(
                $payable->id,
                $request->payment_date,
                $payment->payment_no,
                'supplier_payment',
                $newAmount,
                0,
                $request->notes ?? 'Payment to ' . $newSupplier->business_name,
                $payment
            );
            $this->insertStatementLog(
                $request->account_head_id,
                $request->payment_date,
                $payment->payment_no,
                'supplier_payment',
                0,
                $newAmount,
                $request->notes ?? 'Payment to ' . $newSupplier->business_name,
                $payment
            );

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Payment updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ================== DELETE ==================
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $payment = SupplierPayment::lockForUpdate()->findOrFail($id); // document first: a repeated/simultaneous delete waits here and then finds it gone
            $supplier = Supplier::lockForUpdate()->findOrFail($payment->supplier_id);

            foreach ($payment->purchases as $purchase) {
                $purchase->total_payment_due += $purchase->pivot->paid_amount;
                $purchase->save();
            }
            $supplier->due_amount += $payment->amount;
            $supplier->paid_amount -= $payment->amount;
            $supplier->save();

            $payable = ChartOfAccount::getAccountsPayable();
            if ($payable) {
                $payable->current_balance += $payment->amount;
                $payable->save();
            }
            $account = ChartOfAccount::find($payment->payment_account_id);
            if ($account) {
                $account->current_balance += $payment->amount;
                $account->save();
            }

            // --- REVERSAL LOGS ---
            // Use the ORIGINAL payment date so period reports net to zero
            $reversalDate = date('Y-m-d', strtotime($payment->payment_date));
            if ($payable) {
                $this->insertStatementLog(
                    $payable->id,
                    $reversalDate,
                    $payment->payment_no . '-DEL',
                    'supplier_payment_deletion',
                    0,
                    $payment->amount,
                    'Deletion of payment ' . $payment->payment_no,
                    $payment
                );
            }
            if ($account) {
                $this->insertStatementLog(
                    $account->id,
                    $reversalDate,
                    $payment->payment_no . '-DEL',
                    'supplier_payment_deletion',
                    $payment->amount,
                    0,
                    'Deletion of payment ' . $payment->payment_no,
                    $payment
                );
            }

            // Delete attachments
            if ($payment->attachments) {
                $files = json_decode($payment->attachments, true) ?: [];
                DB::afterCommit(function () use ($files) { // files go only once the deletion is committed
                    foreach ($files as $file) {
                        Storage::disk('public')->delete($file);
                    }
                });
            }
            $payment->delete();
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Payment deleted']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // public function show($id)
    // {
    //     $payment = SupplierPayment::with(['supplier', 'paymentAccount', 'purchases'])->findOrFail($id);
    //     // dd($payment);
    //     return response()->json(['success' => true, 'data' => $payment]);
    // }
public function show($id)
{
    $payment = SupplierPayment::with(['supplier', 'purchases'])->find($id);
    
    if (!$payment) {
        return response()->json([
            'success' => false,
            'message' => 'Payment not found'
        ], 404);
    }
    
    // Manually load the account
    $payment->load('paymentAccount');

    
    // Or manually fetch and append account name
    $accountName = null;
    if ($payment->payment_account_id) {
        $account = ChartOfAccount::find($payment->payment_account_id);
        $accountName = $account ? $account->name : null;
    }
    
    return response()->json([
        'success' => true,
        'data' => $payment,
        'account_name' => $accountName  // Send account name separately
    ]);
}



    public function getSupplierDue($supplierId)
    {
        $supplier = Supplier::find($supplierId);
        if (!$supplier) {
            return response()->json(['success' => false, 'message' => 'Supplier not found']);
        }
        $invoices = Purchase::where('supplier_id', $supplierId)
            ->where('total_payment_due', '>', 0)
            ->orderBy('purchase_date', 'desc')
            ->select('id', 'reference_no as invoice_no', 'purchase_date as date', 'total_payment_due as due_amount')
            ->get();
        return response()->json([
            'success'    => true,
            'due_amount' => $supplier->due_amount,
            'invoices'   => $invoices,
        ]);
    }

    public function getAccountsBySubType($subType)
    {
        $allowed = ['cash', 'bank', 'mobile_bank'];
        if (!in_array($subType, $allowed)) {
            return response()->json([]);
        }
        $accounts = ChartOfAccount::where('type', 'asset')
            ->where('sub_type', $subType)
            ->where('status', 'active')
            ->select('id', 'name', 'current_balance as balance')
            ->get();
        return response()->json($accounts);
    }
}
