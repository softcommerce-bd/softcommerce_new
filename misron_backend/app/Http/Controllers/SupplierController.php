<?php

namespace App\Http\Controllers;

use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\PurchaseReturn;
use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Exception;
use DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\Response;

class SupplierController extends Controller
{
    public function __construct()
    {
        // Authentication is on the route group; these are the per-action role permissions.
        $this->middleware(['permission:view_suppliers'])->only(['supplier_index', 'supplierView', 'fetchLedgerData', 'printLedgerPDF', 'printLedgerPdf', 'fetchSupplierData']);
        $this->middleware(['permission:add_supplier'])->only(['store']);
        $this->middleware(['permission:edit_supplier'])->only(['supplierEdit', 'supplierUpdate']);
        $this->middleware(['permission:delete_supplier'])->only(['supplierDelete']);
        $this->middleware(['permission:make_supplier_payment'])->only(['processPayment']);
    }


    public function processPayment(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'payment_amount' => 'required|numeric|min:0.01'
        ]);

        try {
            DB::beginTransaction();

            $supplier = Supplier::findOrFail($request->supplier_id);
            $paymentAmount = $request->payment_amount;

            // Get total due from all purchases
            $totalDue = $supplier->purchases()->sum('total_payment_due');

            // Validate payment amount doesn't exceed total due
            if ($paymentAmount > $totalDue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds total due amount.'
                ], 422);
            }

            // Update supplier's paid_amount (add to existing paid_amount)
            $supplier->paid_amount = ($supplier->paid_amount ?? 0) + $paymentAmount;
            $supplier->save();

            // Optional: Update individual purchase records
            // This is a simplified approach - you might want to distribute the payment
            // across multiple purchases based on your business logic
            $remainingPayment = $paymentAmount;
            $purchases = $supplier->purchases()
                ->where('total_payment_due', '>', 0)
                ->orderBy('purchase_date', 'asc') // Pay oldest first
                ->get();

            foreach ($purchases as $purchase) {
                if ($remainingPayment <= 0) break;

                $dueAmount = $purchase->total_payment_due;

                if ($dueAmount > 0) {
                    $paymentForThisPurchase = min($dueAmount, $remainingPayment);

                    // Update purchase record
                    $purchase->total_payment_due -= $paymentForThisPurchase;

                    // Add to appropriate payment method field (adjust based on your payment method)
                    $purchase->total_cash_amount = ($purchase->total_cash_amount ?? 0) + $paymentForThisPurchase;

                    // Update payment method array
                    $paymentMethods = json_decode($purchase->payment_method, true) ?? [];
                    $paymentMethods[] = 'cash'; // Add payment method
                    $purchase->payment_method = json_encode(array_unique($paymentMethods));

                    // Update payment dates
                    $paymentDates = json_decode($purchase->advanced_paid_on, true) ?? [];
                    $paymentDates[] = now()->toDateString();
                    $purchase->advanced_paid_on = json_encode($paymentDates);

                    $purchase->save();

                    $remainingPayment -= $paymentForThisPurchase;
                }
            }

            DB::commit();

            // Calculate new total due
            $newTotalDue = $supplier->fresh()->purchases()->sum('total_payment_due');

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'new_total_due' => $newTotalDue,
                'new_total_due_formatted' => number_format($newTotalDue, 2),
                'supplier' => $supplier->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error processing payment: ' . $e->getMessage()
            ], 500);
        }
    }
    // supplier index method 
    public function supplier_index()
    {
        $suppliers = Supplier::all();
        return view('backend.supplier.index', compact('suppliers'));
    }

    // supplier view details 
    public function supplierView($id)
    {
        $supplier = Supplier::find($id);
        $supplierWisePurchased = Purchase::where('supplier_id', $id)->with('products')->get();

        // Default date range: all-time (or you can use today)
        $startDate = $supplierWisePurchased->min('created_at') ? Carbon::parse($supplierWisePurchased->min('created_at'))->startOfDay() : Carbon::today()->startOfDay();
        $endDate = $supplierWisePurchased->max('created_at') ? Carbon::parse($supplierWisePurchased->max('created_at'))->endOfDay() : Carbon::today()->endOfDay();

        // Totals (use the same fields as your fetchLedgerData)
        $totalAdvancedAmount = $supplierWisePurchased->sum('advanced_amount');
        $totalPurchaseAmount = $supplierWisePurchased->sum('net_total_amount');

        $totalPaidAmount = $supplierWisePurchased->sum(function ($purchase) {
            return (
                ($purchase->total_advance_amount ?? 0) +
                ($purchase->total_cash_amount ?? 0) +
                ($purchase->total_cheque_amount ?? 0) +
                ($purchase->total_bank_transfer_amount ?? 0)
            );
        });
        // dd($supplierWisePurchased, $totalPaidAmount);
        $paymentDue = $totalPurchaseAmount - $totalPaidAmount;
        $advanceBalance = max($totalAdvancedAmount - $totalPurchaseAmount, 0);
        $balanceDue = $totalPurchaseAmount - $totalAdvancedAmount;

        return view('backend.supplier.view', compact(
            'supplier',
            'supplierWisePurchased',
            'startDate',
            'endDate',
            'totalAdvancedAmount',
            'totalPurchaseAmount',
            'advanceBalance',
            'totalPaidAmount',
            'paymentDue'
        ));
    }

    // supplier Edit page 
    public function supplierEdit($id)
    {
        $supplier = Supplier::find($id);
        return view('backend.supplier.edit', compact('supplier'));
    }

    // store supplier method
    // public function store(Request $request)
    // {
    //     // Custom validation messages
    //     $messages = [
    //         'business_name.required' => 'The business name is required.',
    //         'mobile.required' => 'The mobile number is required.',
    //         'mobile.numeric' => 'The mobile number must be a valid number.',
    //         'email.email' => 'Please provide a valid email address.',
    //     ];

    //     // Validate the request data
    //     $validatedData = $request->validate([
    //         'business_name' => 'required|string|max:255',
    //         'contact_id' => 'nullable|string|max:255|unique:suppliers,contact_id',
    //         'mobile' => 'required',
    //         'opening_balance' => 'numeric',
    //         'address' => 'nullable|string|max:255',
    //         'contact_person_name' => 'nullable|string|max:255',
    //         'email' => 'nullable|email|max:255|unique:suppliers,email',
    //         'tax_number' => 'nullable|string|max:255',
    //         'pay_term' => 'nullable',
    //     ], $messages);

    //     try {
    //         // Use a transaction to ensure data integrity
    //         DB::beginTransaction();

    //         // Generate a slug for the business name
    //         $slug = Str::slug($request->input('business_name'));

    //         // Create a new supplier using the validated data
    //         $supplier = new Supplier([
    //             'business_name' => $validatedData['business_name'],
    //             'slug' => $slug,
    //             'contact_id' => $validatedData['contact_id'] ?? '',
    //             'mobile' => $validatedData['mobile'] ?? '',
    //             'opening_balance' => $validatedData['opening_balance'] ?? '0',
    //             'address' => $validatedData['address'] ?? '',
    //             'contact_person_name' => $validatedData['contact_person_name'] ?? '',
    //             'email' => $validatedData['email'] ?? '',
    //             'tax_number' => $validatedData['tax_number'] ?? '',
    //             'pay_term' => $validatedData['pay_term'] ?? '',
    //         ]);

    //         // Save the supplier to the database
    //         $supplier->save();

    //         // Commit the transaction
    //         DB::commit();

    //         // Redirect back with a success message
    //         flash(translate('Supplier added successfully!'))->success();
    //         return back();
    //     } catch (\Exception $e) {
    //         // Rollback the transaction if something went wrong
    //         DB::rollBack();

    //         // Log the error for debugging
    //         Log::error('Failed to add supplier: ' . $e->getMessage());

    //         // Redirect back with an error message and old input
    //         return redirect()->back()->withInput()->withErrors(['error' => 'Failed to add supplier. Please try again later.']);
    //     }
    // }

    // public function store(Request $request)
    // {
    //     $messages = [
    //         'business_name.required' => 'The business name is required.',
    //         'mobile.required' => 'The mobile number is required.',
    //         'mobile.numeric' => 'The mobile number must be a valid number.',
    //         'email.email' => 'Please provide a valid email address.',
    //     ];

    //     $validatedData = $request->validate([
    //         'business_name' => 'required|string|max:255',
    //         'contact_id' => 'nullable|string|max:255|unique:suppliers,contact_id',
    //         'mobile' => 'required',
    //         'opening_balance' => 'nullable|numeric',
    //         'address' => 'nullable|string|max:255',
    //         'contact_person_name' => 'nullable|string|max:255',
    //         'email' => 'nullable|email|max:255|unique:suppliers,email',
    //         'tax_number' => 'nullable|string|max:255',
    //         'pay_term' => 'nullable',
    //     ], $messages);

    //     $isDue = $request->has('balance_type') && $request->balance_type == 'due';
    //     $openingBalance = $validatedData['opening_balance'] ?? 0;
    //     $contactId = Supplier::generateContactId();

    //     try {
    //         DB::beginTransaction();

    //         // Create supplier
    //         $supplier = new Supplier([
    //             'business_name' => $validatedData['business_name'],
    //             'slug' => Str::slug($validatedData['business_name']),
    //             'contact_id' => $contactId ?? '',
    //             'mobile' => $validatedData['mobile'] ?? '',
    //             'opening_balance' => $isDue ? 0.00 : $openingBalance,
    //             'due_amount' => $isDue ? $openingBalance : 0,
    //             'address' => $validatedData['address'] ?? '',
    //             'contact_person_name' => $validatedData['contact_person_name'] ?? '',
    //             'email' => $validatedData['email'] ?? '',
    //             'tax_number' => $validatedData['tax_number'] ?? '',
    //             'pay_term' => $validatedData['pay_term'] ?? '',
    //         ]);
    //         $supplier->save();

    //         // Update chart of accounts
    //         if ($isDue) {
    //             // Accounts Payable (Liability)
    //             $account = ChartOfAccount::where('type', 'liability')
    //                 ->where('sub_type', 'payable')
    //                 ->where('name', 'Accounts Payable')
    //                 ->first();

    //             if (!$account) {
    //                 throw new \Exception('Accounts Payable account not found.');
    //             }
    //         } else {
    //             // Opening Supplier Balance account
    //             $account = ChartOfAccount::where('type', 'liability')
    //                 ->where('sub_type', 'equity')
    //                 ->where('name', 'Opening Supplier Balance')
    //                 ->first();;

    //             if (!$account) {
    //                 throw new \Exception('Opening Supplier Balance account not found.');
    //             }
    //         }

    //         // Update account balance
    //         $account->current_balance = ($account->current_balance ?? 0) + $openingBalance;
    //         $account->save();

    //         DB::commit();

    //         flash(translate('Supplier added successfully!'))->success();
    //         return back();
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Failed to add supplier: ' . $e->getMessage());
    //         return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage() ?: 'Failed to add supplier.']);
    //     }
    // }

    // // update supplier method
    // public function supplierUpdate(Request $request, $id)
    // {
    //     $supplier = Supplier::find($id);

    //     if (!$supplier) {
    //         return redirect()->back()->withErrors(['error' => 'Supplier not found.']);
    //     }

    //     // Custom validation messages
    //     $messages = [
    //         'business_name.required' => 'The business name is required.',
    //         'mobile.required' => 'The mobile number is required.',
    //         'mobile.numeric' => 'The mobile number must be a valid number.',
    //         'email.email' => 'Please provide a valid email address.',
    //     ];

    //     // Validate the request data
    //     $validatedData = $request->validate([
    //         'business_name' => 'required|string|max:255',
    //         'contact_id' => 'required|string|max:255|unique:suppliers,contact_id,' . $id,
    //         'mobile' => 'required|numeric',
    //         'opening_balance' => 'required|numeric',
    //         'address' => 'nullable|string|max:255',
    //         'contact_person_name' => 'nullable|string|max:255',
    //         'email' => 'nullable|email|max:255|unique:suppliers,email,' . $id,
    //         'tax_number' => 'nullable|string|max:255',
    //         'pay_term' => 'nullable',
    //     ], $messages);

    //     try {
    //         // Use a transaction to ensure data integrity
    //         DB::beginTransaction();

    //         // Update the supplier's details
    //         $supplier->update([
    //             'business_name' => $validatedData['business_name'],
    //             'slug' => Str::slug($validatedData['business_name']),
    //             'contact_id' => $validatedData['contact_id'],
    //             'mobile' => $validatedData['mobile'],
    //             'opening_balance' => $validatedData['opening_balance'],
    //             'address' => $validatedData['address'],
    //             'contact_person_name' => $validatedData['contact_person_name'],
    //             'email' => $validatedData['email'],
    //             'tax_number' => $validatedData['tax_number'],
    //             'pay_term' => $validatedData['pay_term'],
    //         ]);

    //         // Commit the transaction
    //         DB::commit();

    //         // Redirect back with a success message
    //         flash(translate('Supplier updated successfully!'))->success();
    //         return redirect()->route('suppliers.index');
    //     } catch (\Exception $e) {
    //         // Rollback the transaction if something went wrong
    //         DB::rollBack();
    //         // Log the error for debugging
    //         Log::error('Failed to update supplier: ' . $e->getMessage());

    //         flash(translate('Failed to update supplier. Please try again later.'))->error();
    //         return back();

    //         // Redirect back with an error message
    //         // return redirect()->back()->withInput()->withErrors(['error' => 'Failed to update supplier. Please try again later.']);
    //     }
    // }

    public function store(Request $request)
{
    $messages = [
        'business_name.required' => 'The business name is required.',
        'mobile.required' => 'The mobile number is required.',
        'mobile.numeric' => 'The mobile number must be a valid number.',
        'email.email' => 'Please provide a valid email address.',
    ];

    $validatedData = $request->validate([
        'business_name' => 'required|string|max:255',
        'contact_id' => 'nullable|string|max:255|unique:suppliers,contact_id',
        'mobile' => 'required',
        'opening_balance' => 'nullable|numeric',
        'address' => 'nullable|string|max:255',
        'contact_person_name' => 'nullable|string|max:255',
        'email' => 'nullable|email|max:255|unique:suppliers,email',
        'tax_number' => 'nullable|string|max:255',
        'pay_term' => 'nullable',
    ], $messages);

    $isDue = $request->has('balance_type') && $request->balance_type == 'due';
    $openingBalance = $validatedData['opening_balance'] ?? 0;
    $contactId = Supplier::generateContactId();

    try {
        DB::beginTransaction();

        // Create supplier
        $supplier = new Supplier([
            'business_name' => $validatedData['business_name'],
            'slug' => Str::slug($validatedData['business_name']),
            'contact_id' => $contactId ?? '',
            'mobile' => $validatedData['mobile'] ?? '',
            'opening_balance' => $isDue ? 0.00 : $openingBalance,
            'due_amount' => $isDue ? $openingBalance : 0,
            'address' => $validatedData['address'] ?? '',
            'contact_person_name' => $validatedData['contact_person_name'] ?? '',
            'email' => $validatedData['email'] ?? '',
            'tax_number' => $validatedData['tax_number'] ?? '',
            'pay_term' => $validatedData['pay_term'] ?? '',
        ]);
        $supplier->save();

        // Update chart of accounts
        if ($isDue) {
            // Accounts Payable (Liability)
            $account = ChartOfAccount::where('type', 'liability')
                ->where('sub_type', 'payable')
                ->where('name', 'Accounts Payable')
                ->first();

            if (!$account) {
                throw new \Exception('Accounts Payable account not found.');
            }
            
            $transactionType = 'supplier_opening_due';
            $description = 'Opening due balance for supplier: ' . $supplier->business_name;
        } else {
            // Opening Supplier Balance account
            $account = ChartOfAccount::where('type', 'liability')
                ->where('sub_type', 'equity')
                ->where('name', 'Opening Supplier Balance')
                ->first();

            if (!$account) {
                throw new \Exception('Opening Supplier Balance account not found.');
            }
            
            $transactionType = 'supplier_opening_balance';
            $description = 'Opening balance for supplier: ' . $supplier->business_name;
        }

        // Update account balance
        $oldBalance = $account->current_balance ?? 0;
        $account->current_balance = $oldBalance + $openingBalance;
        $account->save();

        // Insert statement log
        $this->insertSupplierStatementLog(
            $account->id,
            now()->toDateString(),
            $supplier->contact_id,
            $transactionType,
            0, // debit
            $openingBalance, // credit (increase liability)
            $description,
            $supplier
        );

        DB::commit();

        flash(translate('Supplier added successfully!'))->success();
        return back();
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to add supplier: ' . $e->getMessage());
        return redirect()->back()->withInput()->withErrors(['error' => $e->getMessage() ?: 'Failed to add supplier.']);
    }
}

// update supplier method
public function supplierUpdate(Request $request, $id)
{
    $supplier = Supplier::find($id);

    if (!$supplier) {
        return redirect()->back()->withErrors(['error' => 'Supplier not found.']);
    }

    // Custom validation messages
    $messages = [
        'business_name.required' => 'The business name is required.',
        'mobile.required' => 'The mobile number is required.',
        'mobile.numeric' => 'The mobile number must be a valid number.',
        'email.email' => 'Please provide a valid email address.',
    ];

    // Validate the request data
    $validatedData = $request->validate([
        'business_name' => 'required|string|max:255',
        'contact_id' => 'required|string|max:255|unique:suppliers,contact_id,' . $id,
        'mobile' => 'required|numeric',
        'opening_balance' => 'required|numeric',
        'address' => 'nullable|string|max:255',
        'contact_person_name' => 'nullable|string|max:255',
        'email' => 'nullable|email|max:255|unique:suppliers,email,' . $id,
        'tax_number' => 'nullable|string|max:255',
        'pay_term' => 'nullable',
    ], $messages);

    try {
        // Use a transaction to ensure data integrity
        DB::beginTransaction();

        // Store old values for comparison
        $oldOpeningBalance = $supplier->opening_balance;
        $oldDueAmount = $supplier->due_amount;
        $newOpeningBalance = $validatedData['opening_balance'];

        // Determine if it's due or balance
        $isDue = $request->has('balance_type') && $request->balance_type == 'due';

        // Update the supplier's details
        $supplier->update([
            'business_name' => $validatedData['business_name'],
            'slug' => Str::slug($validatedData['business_name']),
            'contact_id' => $validatedData['contact_id'],
            'mobile' => $validatedData['mobile'],
            'opening_balance' => $isDue ? 0.00 : $newOpeningBalance,
            'due_amount' => $isDue ? $newOpeningBalance : $supplier->due_amount,
            'address' => $validatedData['address'],
            'contact_person_name' => $validatedData['contact_person_name'],
            'email' => $validatedData['email'],
            'tax_number' => $validatedData['tax_number'],
            'pay_term' => $validatedData['pay_term'],
        ]);

        // Handle accounting entries if opening balance changed
        if ($oldOpeningBalance != $newOpeningBalance || $isDue) {
            $transactionDate = now()->toDateString();
            $referenceNo = $supplier->contact_id . '-UPDATE';

            if ($isDue) {
                // Handle due balance changes
                $accountsPayable = ChartOfAccount::where('type', 'liability')
                    ->where('sub_type', 'payable')
                    ->where('name', 'Accounts Payable')
                    ->first();

                if (!$accountsPayable) {
                    throw new \Exception('Accounts Payable account not found.');
                }

                // Calculate the difference
                $oldTotalDue = $oldDueAmount;
                $newTotalDue = $newOpeningBalance;
                $dueDifference = $newTotalDue - $oldTotalDue;

                if ($dueDifference != 0) {
                    // Reverse old due if exists
                    if ($oldTotalDue > 0) {
                        $accountsPayable->current_balance -= $oldTotalDue;
                        
                        // Reversal log
                        $this->insertSupplierStatementLog(
                            $accountsPayable->id,
                            $transactionDate,
                            $referenceNo,
                            'supplier_due_reversal',
                            $oldTotalDue, // debit (reverse credit)
                            0, // credit
                            'Reversal of old due amount for supplier: ' . $supplier->business_name,
                            $supplier
                        );
                    }

                    // Apply new due
                    if ($newTotalDue > 0) {
                        $accountsPayable->current_balance += $newTotalDue;
                        
                        // New due log
                        $this->insertSupplierStatementLog(
                            $accountsPayable->id,
                            $transactionDate,
                            $referenceNo,
                            'supplier_due_update',
                            0, // debit
                            $newTotalDue, // credit (increase liability)
                            'Updated due amount for supplier: ' . $supplier->business_name,
                            $supplier
                        );
                    }

                    $accountsPayable->save();
                }
            } else {
                // Handle opening balance changes
                $openingBalanceAccount = ChartOfAccount::where('type', 'liability')
                    ->where('sub_type', 'equity')
                    ->where('name', 'Opening Supplier Balance')
                    ->first();

                if (!$openingBalanceAccount) {
                    throw new Exception('Opening Supplier Balance account not found.');
                }

                $balanceDifference = $newOpeningBalance - $oldOpeningBalance;

                if ($balanceDifference != 0) {
                    // Reverse old balance
                    if ($oldOpeningBalance > 0) {
                        $openingBalanceAccount->current_balance -= $oldOpeningBalance;
                        
                        // Reversal log
                        $this->insertSupplierStatementLog(
                            $openingBalanceAccount->id,
                            $transactionDate,
                            $referenceNo,
                            'supplier_balance_reversal',
                            $oldOpeningBalance, // debit (reverse credit)
                            0, // credit
                            'Reversal of old opening balance for supplier: ' . $supplier->business_name,
                            $supplier
                        );
                    }

                    // Apply new balance
                    if ($newOpeningBalance > 0) {
                        $openingBalanceAccount->current_balance += $newOpeningBalance;
                        
                        // New balance log
                        $this->insertSupplierStatementLog(
                            $openingBalanceAccount->id,
                            $transactionDate,
                            $referenceNo,
                            'supplier_balance_update',
                            0, // debit
                            $newOpeningBalance, // credit (increase liability)
                            'Updated opening balance for supplier: ' . $supplier->business_name,
                            $supplier
                        );
                    }

                    $openingBalanceAccount->save();
                }
            }
        }

        // Commit the transaction
        DB::commit();

        // Redirect back with a success message
        flash(translate('Supplier updated successfully!'))->success();
        return redirect()->route('suppliers.index');
    } catch (\Exception $e) {
        // Rollback the transaction if something went wrong
        DB::rollBack();
        // Log the error for debugging
        Log::error('Failed to update supplier: ' . $e->getMessage());

        flash(translate('Failed to update supplier. Please try again later.'))->error();
        return back();
    }
}

/**
 * Insert statement log for supplier transactions
 */
private function insertSupplierStatementLog(
    $accountId,
    $date,
    $referenceNo,
    $type,
    $debit,
    $credit,
    $desc,
    $supplier
) {
    try {
        $lastLog = AccountStatementLog::where('account_id', $accountId)
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $previousBalance = $lastLog ? $lastLog->balance : 0;
        $newBalance = $previousBalance + $debit - $credit;

        return AccountStatementLog::create([
            'account_id'       => $accountId,
            'transaction_date' => $date,
            'reference_no'     => $referenceNo,
            'transaction_type' => $type,
            'debit'            => $debit,
            'credit'           => $credit,
            'balance'          => $newBalance,
            'description'      => $desc,
            'source_id'        => $supplier->id,
            'source_type'      => get_class($supplier),
            'voucher_id'       => null,
            'order_id'         => null,
        ]);
    } catch (\Exception $e) {
        \Log::error('Failed to insert supplier statement log: ' . $e->getMessage());
        throw $e;
    }
}

  // delete supplier (with transaction check)
public function supplierDelete($id)
{
    $supplier = Supplier::find($id);

    if (!$supplier) {
        flash(translate('Supplier not found.'))->error();
        return back();
    }

    try {
        DB::beginTransaction();

        // Check if supplier has any pending transactions
        $hasPurchases = Purchase::where('supplier_id', $supplier->id)->exists();
        $hasPayments = SupplierPayment::where('supplier_id', $supplier->id)->exists();
        $hasReturns = PurchaseReturn::where('supplier_id', $supplier->id)->exists();

        if ($hasPurchases || $hasPayments || $hasReturns) {
            $transactionTypes = [];
            if ($hasPurchases) $transactionTypes[] = 'purchases';
            if ($hasPayments) $transactionTypes[] = 'payments';
            if ($hasReturns) $transactionTypes[] = 'returns';
            
            throw new \Exception('Cannot delete supplier. Existing ' . implode(', ', $transactionTypes) . ' found for this supplier.');
        }

        $transactionDate = now()->toDateString();
        $referenceNo = $supplier->contact_id . '-DELETE';

        // Reverse due amount if exists
        if ($supplier->due_amount > 0) {
            $accountsPayable = ChartOfAccount::where('type', 'liability')
                ->where('sub_type', 'payable')
                ->where('name', 'Accounts Payable')
                ->first();

            if ($accountsPayable) {
                $accountsPayable->current_balance -= $supplier->due_amount;
                $accountsPayable->save();

                $this->insertSupplierStatementLog(
                    $accountsPayable->id,
                    $transactionDate,
                    $referenceNo,
                    'supplier_due_deletion',
                    $supplier->due_amount,
                    0,
                    'Due amount reversed - Supplier deleted: ' . $supplier->business_name . ' (Contact ID: ' . $supplier->contact_id . ')',
                    $supplier
                );

                \Log::info("Reversed due amount {$supplier->due_amount} from Accounts Payable for deleted supplier: {$supplier->business_name}");
            }
        }

        // Reverse opening balance if exists
        if ($supplier->opening_balance > 0) {
            $openingBalanceAccount = ChartOfAccount::where('type', 'liability')
                ->where('sub_type', 'equity')
                ->where('name', 'Opening Supplier Balance')
                ->first();

            if ($openingBalanceAccount) {
                $openingBalanceAccount->current_balance -= $supplier->opening_balance;
                $openingBalanceAccount->save();

                $this->insertSupplierStatementLog(
                    $openingBalanceAccount->id,
                    $transactionDate,
                    $referenceNo,
                    'supplier_balance_deletion',
                    $supplier->opening_balance,
                    0,
                    'Opening balance reversed - Supplier deleted: ' . $supplier->business_name . ' (Contact ID: ' . $supplier->contact_id . ')',
                    $supplier
                );

                \Log::info("Reversed opening balance {$supplier->opening_balance} from Opening Supplier Balance for deleted supplier: {$supplier->business_name}");
            }
        }

        // Delete the supplier
        $supplier->delete();

        DB::commit();

        flash(translate('Supplier deleted successfully!'))->success();
        \Log::info("Supplier deleted successfully: {$supplier->business_name} (ID: {$id})");

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Failed to delete supplier: ' . $e->getMessage());
        flash(translate($e->getMessage()))->error();
    }

    return back();
}


    // Ledger supplier date wise sort 

    public function fetchLedgerData(Request $request)
    {
        $dateRange = $request->input('ledger_date_range');
        $supplierId = $request->input('supplier_id');
        $supplier = Supplier::find($supplierId);
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        if (empty($startDate) || empty($endDate)) {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        } else {
            if ($startDate === $endDate) {
                $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate))->startOfDay();
                $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate))->endOfDay();
            } else {
                $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate))->startOfDay();
                $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate))->endOfDay();
            }
        }

        $supplierWisePurchased = Purchase::where('supplier_id', $supplierId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('products')
            ->get();

        $totalAdvancedAmount = $supplierWisePurchased->sum('advanced_amount');
        $totalPurchaseAmount = $supplierWisePurchased->sum('net_total_amount');

        $totalPaidAmount = $supplierWisePurchased->sum(function ($purchase) {
            return (
                ($purchase->total_advance_amount ?? 0) +
                ($purchase->total_cash_amount ?? 0) +
                ($purchase->total_cheque_amount ?? 0) +
                ($purchase->total_bank_transfer_amount ?? 0)
            );
        });
        // dd($supplierWisePurchased, $totalPaidAmount);
        $paymentDue = $totalPurchaseAmount - $totalPaidAmount;
        $advanceBalance = $totalAdvancedAmount - $totalPurchaseAmount;
        $balanceDue = $totalPurchaseAmount - $totalAdvancedAmount;

        // Ensure all necessary variables are included here
        $tablesHtml = view('backend.supplier.ledger', [
            'supplier' => $supplier,
            'totalPurchaseAmount' => $totalPurchaseAmount,
            'totalAdvancedAmount' => $totalAdvancedAmount,
            'advanceBalance' => $advanceBalance,
            'totalPaidAmount' => $totalPaidAmount,
            'balanceDue' => $balanceDue,
            'paymentDue' => $paymentDue,
            'supplierWisePurchased' => $supplierWisePurchased,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->render();

        return response()->json([
            'tablesHtml' => $tablesHtml
        ]);
    }

    // ledger pdf download 


    // public function printLedgerPDF(Request $request)
    // {
    //     dd($request->all());
    //     $supplier = Supplier::find($request->supplier_id); // Fetch supplier data
    //     $supplierWisePurchased = Purchase::where('supplier_id', $request->supplier_id)->get(); // Fetch supplier purchase data
    //     $totalPurchaseAmount = $supplierWisePurchased->sum('total_item'); // Calculate total purchases
    //     $totalAdvancedAmount = $supplierWisePurchased->sum('advanced_amount'); // Calculate total advanced payments

    //     // Load the view with data
    //     $html = view('backend.supplier.ledger', compact('supplier', 'supplierWisePurchased', 'totalPurchaseAmount', 'totalAdvancedAmount'))->render();

    //     // Initialize Mpdf
    //     $mpdf = new Mpdf();
    //     $mpdf->WriteHTML($html);

    //     // Output PDF file
    //     return $mpdf->Output('ledger.pdf', 'I'); // 'I' for inline display, 'D' for download
    // }


    // public function printLedgerPDF(Request $request)
    // {
    //     dd($request->all());
    //     $supplier = Supplier::find($request->supplier_id);  // Fetch supplier data
    //     $supplierWisePurchased = Purchase::where('supplier_id', $supplier->id)->get();  // Fetch purchases for the supplier

    //     // Calculate totals
    //     $totalPurchaseAmount = $supplierWisePurchased->sum('total_cost');
    //     $totalAdvancedAmount = $supplierWisePurchased->sum('advanced_amount');
    //     $balanceDue = $totalPurchaseAmount - $totalAdvancedAmount;

    //     // Create the PDF
    //     $pdf = new Mpdf();
    //     $html = view('backend.supplier.ledger', compact('supplier', 'supplierWisePurchased', 'totalPurchaseAmount', 'totalAdvancedAmount', 'balanceDue'))->render();

    //     $pdf->WriteHTML($html);

    //     // Save the PDF temporarily
    //     $pdfFilePath = storage_path('app/public/ledger.pdf');
    //     $pdf->Output($pdfFilePath, \Mpdf\Output\Destination::FILE);

    //     // Return the file as a response
    //     return Response::json(['pdf_url' => asset('storage/ledger.pdf')]);
    // }

    public function printLedgerPdf(Request $request)
    {
        $dateRange = $request->input('ledger_date_range');
        $supplierId = $request->input('supplier_id');
        $supplier = Supplier::find($supplierId);

        if (!$dateRange) {
            $startDate = Carbon::today()->startOfDay();
            $endDate = Carbon::today()->endOfDay();
        } else {
            list($startDate, $endDate) = explode(' - ', $dateRange);
            $startDate = Carbon::createFromFormat('m/d/Y', $startDate)->startOfDay();
            $endDate = Carbon::createFromFormat('m/d/Y', $endDate)->endOfDay();
        }

        $supplierWisePurchased = Purchase::where('supplier_id', $supplierId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('products')
            ->get();

        $totalAdvancedAmount = $supplierWisePurchased->sum('advanced_amount');
        $totalPurchaseAmount = $supplierWisePurchased->sum('net_total_amount');
        $advanceBalance = $totalAdvancedAmount - $totalPurchaseAmount;
        $balanceDue = $totalPurchaseAmount - $totalAdvancedAmount;

        $pdf = new Mpdf();
        $pdf->WriteHTML(view('backend.supplier.ledger', [
            'supplier' => $supplier,
            'totalPurchaseAmount' => $totalPurchaseAmount,
            'totalAdvancedAmount' => $totalAdvancedAmount,
            'advanceBalance' => $advanceBalance,
            'balanceDue' => $balanceDue,
            'supplierWisePurchased' => $supplierWisePurchased,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->render());

        return $pdf->Output('ledger.pdf', 'I');
    }



    // supplier purchase history 

    public function fetchSupplierData(Request $request)
    {
        $startDate = Carbon::createFromFormat('m/d/Y', $request->input('start_date'))->startOfDay();
        $endDate = Carbon::createFromFormat('m/d/Y', $request->input('end_date'))->endOfDay();
        $supplierId = $request->input('supplier_id');
        $supplierWisePurchased = Purchase::where('supplier_id', $supplierId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return response()->json(['data' => $supplierWisePurchased]);
    }
}
