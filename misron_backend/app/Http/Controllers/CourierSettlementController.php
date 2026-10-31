<?php

namespace App\Http\Controllers;

use App\Models\AccountStatementLog;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ChartOfAccount;
use App\Models\SteadfastSetting;
use App\Models\PathaoCourierSetting;
use App\Models\CourierSettlement;
use App\Models\CourierSettlementDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use App\Models\PurchaseProduct;

class CourierSettlementController extends Controller
{
    public function __construct()
    {
        // Authentication is on the route group; these are the per-action role permissions.
        $this->middleware(['permission:view_courier_settlements'])->only(['index', 'getSettlementDetails', 'report', 'exportPDF', 'details']);
        $this->middleware(['permission:settle_courier_orders'])->only(['settle', 'unsettle']);
    }


    public function details($id)
    {
        $settlement = CourierSettlement::with('settlementDetails')
            ->findOrFail($id);

        return view('backend.accounts.customer_payments', compact('settlement'));
    }
    public function index()
    {
        // Get orders where courier_name is not null
        // Paginated + light eager loading so the page opens fast even with many orders.
        // pending_items_count replaces loading every order's full product list.
        $orders = Order::with('settlement')
            ->withCount(['orderDetails as pending_items_count' => function ($query) {
                $query->where('delivery_status', 'pending');
            }])
            ->withSum(['orderDetails as shipping_cost_sum' => function ($query) {
                $query->whereIn('delivery_status', ['delivered', 'exchange', 'return']);
            }], 'shipping_cost')
            ->whereNotNull('courier_name')
            ->whereIn('courier_status', ['delivered', 'partial_delivered'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Get accounts from chart_of_accounts table
        $cashAccounts = ChartOfAccount::where('sub_type', 'cash')
            ->where('status', 'active')
            ->get();

        $bankAccounts = ChartOfAccount::where('sub_type', 'bank')
            ->where('status', 'active')
            ->get();

        $mobileBankingAccounts = ChartOfAccount::where('sub_type', 'mobile_bank')
            ->where('status', 'active')
            ->get();

        // Get courier settings
        $steadfast = SteadfastSetting::first();
        $pathao = PathaoCourierSetting::first();

        return view('backend.courier_settlement.index', compact(
            'orders',
            'cashAccounts',
            'bankAccounts',
            'mobileBankingAccounts',
            'steadfast',
            'pathao'
        ));
    }

    public function exportPDF(Request $request)
    {
        try {
            // Build query with filters
            $query = Order::whereNotNull('courier_name');

            // Apply search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'LIKE', "%{$search}%")
                        ->orWhere('shipping_address', 'LIKE', "%{$search}%");
                });
            }

            // Apply status filter
            if ($request->has('payment_status') && !empty($request->payment_status)) {
                if ($request->payment_status == 'paid') {
                    $query->where('payment_status', 'paid');
                } elseif ($request->payment_status == 'unpaid') {
                    $query->where('payment_status', 'unpaid')
                        ->where('partial_paid_amount', '<=', 0);
                } elseif ($request->payment_status == 'partial') {
                    $query->where('partial_paid_amount', '>', 0);
                }
            }

            $orders = $query->orderBy('created_at', 'desc')->get();

            // Calculate totals
            $totals = [
                'due_amount' => 0,
                'courier_fee' => 0,
                'settlement_fee' => 0,
                'settled_count' => 0,
                'unsettled_count' => 0,
                'net_payable' => 0
            ];

            foreach ($orders as $order) {
                $totals['due_amount'] += $order->due_amount;
                $totals['courier_fee'] += $order->courier_fee ?? 0;
                $totals['settlement_fee'] += $order->settlement_fee ?? 0;

                if ($order->settlement_status == 'true') {
                    $totals['settled_count']++;
                } else {
                    $totals['unsettled_count']++;
                }

                $totals['net_payable'] += ($order->due_amount - ($order->settlement_fee ?? 0) - ($order->courier_fee ?? 0));
            }

            // Filters for display
            $filters = [
                'search' => $request->search,
                'payment_status' => $request->payment_status
            ];

            // Generate PDF
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_header' => 5,
                'margin_footer' => 5
            ]);

            // Set header
            $mpdf->SetHTMLHeader('
                <div style="text-align: right; font-size: 8px; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px;">
                    Courier Settlement Report | Page {PAGENO}
                </div>
            ');

            // Load view and generate PDF
            $html = view('backend.courier_settlement.export_pdf', compact('orders', 'totals', 'filters'))->render();
            $mpdf->WriteHTML($html);

            // Download PDF
            $filename = 'courier_settlement_report_' . date('Y-m-d_H-i-s') . '.pdf';
            $mpdf->Output($filename, 'D');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ]);
        }
    }


/** Ids of every account a settlement can touch, ascending (the selected one, receivable, inventory, COGS, sales, courier income and the expense accounts). */
private function ledgerAccountIds(int $selected): array
{
    $ids = [$selected];
    foreach (['getAccountsReceivable', 'getInventoryAccount', 'getSalesAccount', 'getCourierIncomeAccount', 'getBadDebtExpenseAccount', 'getCourierSettlementExpenseAccount', 'getCourierFeeExpenseAccount'] as $getter) {
        if (method_exists(ChartOfAccount::class, $getter) && ($a = ChartOfAccount::$getter())) {
            $ids[] = $a->id;
        }
    }
    $cogs = ChartOfAccount::where('type', 'expense')->where('name', 'Cost of Goods Sold')->first() ?? ChartOfAccount::where('sub_type', 'cost_of_sales')->first();
    if ($cogs) {
        $ids[] = $cogs->id;
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids);

    return $ids;
}

public function settle(Request $request)
{
    $request->validate([
        'order_id' => 'required|exists:orders,id',
        'courier_fee' => 'required|numeric|min:0',
        'account_id' => 'required|exists:chart_of_accounts,id',
        'courier_name' => 'required',
        'settlement_fee' => 'required|numeric',
        'due_amount' => 'required|numeric',
        'settlement_charge' => 'required|numeric',
        'partial_payment_choice' => 'nullable|in:customer,admin',
        'given_customer' => 'nullable|numeric|min:0',
        'actual_courier_fee' => 'nullable|numeric|min:0'
    ]);

    // Accounts this settlement may change, found BEFORE the transaction so they can be locked by primary key (in id order) as its first statements.
    $ledgerIds = $this->ledgerAccountIds((int) $request->account_id);

    try {
        DB::beginTransaction();

        // Lock the order and the accounts first: two admins settling the same order, or two orders into one account, then queue instead of
        // both reading the old state (a double settlement, or a balance written from a stale read). No plain read precedes these locks, so the
        // reads that follow see what the previous holder committed.
        $order = Order::whereKey($request->order_id)->lockForUpdate()->firstOrFail();
        ChartOfAccount::whereIn('id', $ledgerIds)->orderBy('id')->lockForUpdate()->get(['id']);
        $account = ChartOfAccount::findOrFail($request->account_id);

        Log::info('=== SETTLEMENT ATTEMPT START ===', [
            'order_id' => $order->id,
            'order_code' => $order->code,
            'courier_status' => $order->courier_status,
            'due_amount' => $order->due_amount,
            'settlement_status' => $order->settlement_status
        ]);

        // Check if already settled
        if ($order->settlement_status == 'true' || $order->settlement_status == 1) {
            DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
            return response()->json([
                'success' => false,
                'message' => 'This order has already been settled'
            ]);
        }

        // Prevent settlement of cancelled or fully reversed orders
        if ($order->courier_status === 'cancelled' || $order->delivery_status === 'cancelled') {
            DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
            return response()->json([
                'success' => false,
                'message' => 'Cancelled orders cannot be settled'
            ]);
        }

        // Define partial delivery status ONCE here
        $isPartialDelivered = ($order->courier_status == 'partial_delivered');

        // For partial_delivered orders, due_amount may be 0
        // Allow settlement based on shipping cost collected by courier
        if (!$isPartialDelivered && $order->due_amount <= 0) {
            DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
            return response()->json([
                'success' => false,
                'message' => 'Order has no due amount to settle'
            ]);
        }

        // Prevent settlement if all items are cancelled (no deliverable items)
        $hasDeliverableItem = OrderDetail::where('order_id', $order->id)
            ->where('delivery_status', '!=', 'cancelled')
            ->exists();
        if (!$hasDeliverableItem) {
            DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
            return response()->json([
                'success' => false,
                'message' => 'All items in this order are cancelled — nothing to settle'
            ]);
        }

        // Check if order is paid and delivered
        $isPaidAndDelivered = ($order->payment_status == 'paid' && $order->courier_status == 'delivered');

        // For partial_delivered orders, require that all items have been reviewed
        // Only 'pending' and 'confirmed' are considered not reviewed
        // 'delivered', 'exchange', 'return', 'cancelled' are considered reviewed
        if ($isPartialDelivered) {
            $pendingItems = OrderDetail::where('order_id', $order->id)
                ->whereIn('delivery_status', ['pending', 'confirmed'])
                ->exists();
            if ($pendingItems) {
                DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                return response()->json([
                    'success' => false,
                    'message' => 'This order has pending items. Please open Details and update item statuses (delivered/cancelled) before settling.'
                ]);
            }
        }

        // Skip duplicate Sales/COGS/Inventory entries for POS orders
        $isPosAlreadyAccounted = ($order->order_from == 'pos');
        $isPosFullyPaid = ($order->order_from == 'pos' && $order->due_amount <= 0);

        // Get order details (product eager loaded to avoid N+1)
        $orderDetails = OrderDetail::with('product')->where('order_id', $order->id)->get();

        // Calculate total shipping cost from order details
        // For partial delivery: include delivered, exchange, AND return items
        // (exchange/return means courier originally delivered the item, so shipping cost applies)
        if ($isPartialDelivered) {
            $totalShippingCost = $orderDetails
                ->whereIn('delivery_status', ['delivered', 'exchange', 'return'])
                ->sum('shipping_cost');
        } else {
            $totalShippingCost = $orderDetails->sum('shipping_cost');
        }

        Log::info('Shipping cost calculation', [
            'total_shipping_cost' => $totalShippingCost,
            'is_partial' => $isPartialDelivered ? 'YES' : 'NO',
            'counted_statuses' => $isPartialDelivered ? ['delivered', 'exchange', 'return'] : 'all'
        ]);

        // Load purchase products for these order details in ONE query (avoid N+1)
        $purchaseProductMap = [];
        $purchaseIds = $orderDetails->pluck('purchase_id')->filter()->unique()->values();
        if ($purchaseIds->isNotEmpty()) {
            foreach (PurchaseProduct::whereIn('purchase_id', $purchaseIds)->get() as $pp) {
                $purchaseProductMap[$pp->purchase_id . '|' . $pp->product_id . '|' . $pp->variant] = $pp;
            }
        }

        // Calculate total COGS from order details using purchase products
        $totalCOGS = 0;
        $inventoryReductionDetails = [];

        foreach ($orderDetails as $detail) {
            // For partial delivered orders, count delivered, exchange, AND return items for COGS
            // (exchange/return items were originally sold, so COGS applies)
            if ($isPartialDelivered && !in_array($detail->delivery_status, ['delivered', 'exchange', 'return'])) {
                continue;
            }
            // Lines of the confirmed-order stock model carry the exact weighted FIFO cost recorded when the goods were dispatched
            // (possibly across several cost layers). A line with units that had no cost layer ('uncosted') is not given an invented cost.
            if (($detail->stock_mode ?? 'legacy') === 'v2') {
                if ($detail->cost_status === 'final' && $detail->quantity > 0) {
                    $lineCOGS = round((float) $detail->buying_price * $detail->quantity, 2);
                    $totalCOGS += $lineCOGS;
                    $inventoryReductionDetails[] = [
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product->name ?? 'Product-' . $detail->product_id,
                        'variant' => $detail->variation,
                        'quantity' => $detail->quantity,
                        'unit_cost' => (float) $detail->buying_price,
                        'total_cost' => $lineCOGS,
                        'purchase_id' => $detail->purchase_id,
                    ];
                }
                continue;
            }
            if ($detail->purchase_id && $detail->quantity > 0) {
                $purchaseProduct = $purchaseProductMap[$detail->purchase_id . '|' . $detail->product_id . '|' . $detail->variation] ?? null;

                if ($purchaseProduct) {
                    $unitCost = $purchaseProduct->buying_unit_cost;
                    $lineCOGS = $unitCost * $detail->quantity;
                    $totalCOGS += $lineCOGS;

                    $inventoryReductionDetails[] = [
                        'product_id' => $detail->product_id,
                        'product_name' => $detail->product->name ?? 'Product-' . $detail->product_id,
                        'variant' => $detail->variation,
                        'quantity' => $detail->quantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => $lineCOGS,
                        'purchase_id' => $detail->purchase_id
                    ];
                }
            }
        }

        // Get input values
        $courierFee = $request->courier_fee;
        $settlementCharge = $request->settlement_charge;
        $partialPaymentChoice = $request->partial_payment_choice;

        // Zero-shipping partial delivery (courier collected nothing via shipping cost).
        // When the customer pays the courier directly, two manual inputs are used:
        // given_customer = amount collected from the customer,
        // actual_courier_fee = the real courier charge.
        $isZeroShippingPartial = $isPartialDelivered && $totalShippingCost <= 0;
        $isCustomerPaidZeroShipping = $isZeroShippingPartial && $partialPaymentChoice == 'customer';
        $isAdminPaidZeroShipping = $isZeroShippingPartial && $partialPaymentChoice == 'admin';

        // ==========================================
        // SETTLEMENT FEE CALCULATION
        // ==========================================
        if ($isCustomerPaidZeroShipping) {
            $givenCustomer = (float) $request->given_customer;
            $actualCourierFee = (float) $request->actual_courier_fee;

            if ($givenCustomer <= 0) {
                DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter the amount given by customer'
                ]);
            }

            $courierFee = $actualCourierFee;
            $totalAmount = $givenCustomer;
            // Settlement fee = (given by customer - actual courier fee) * settlement charge %
            $settlementFee = (($givenCustomer - $actualCourierFee) * $settlementCharge) / 100;
        } elseif ($isAdminPaidZeroShipping) {
            // Admin pays the courier directly: nothing collected from the customer,
            // so no settlement fee is calculated (actual fee goes to Bad Debt Expense)
            $totalAmount = 0;
            $settlementFee = 0;
        } elseif ($isPartialDelivered) {
            // For partial delivery, the settlement is based on the full order total
            // (grand_total), not just the shipping cost.
            // Settlement fee = (total - courier fee) * settlement charge %
            $totalAmount = $order->grand_total > 0 ? $order->grand_total
                : ($totalShippingCost > 0 ? $totalShippingCost : $order->due_amount);
            $settlementFee = (($totalAmount - $courierFee) * $settlementCharge) / 100;
        } else {
            // For full delivery: use the request settlement_fee directly
            $settlementFee = $request->settlement_fee;
            $totalAmount = $order->due_amount;
        }

        // Calculate net amount to add to selected account
        $netAmount = $totalAmount - ($settlementFee + $courierFee);

        // For zero-shipping partial deliveries where admin pays the courier directly,
        // nothing is collected, so no amount is credited to the selected account
        if ($isAdminPaidZeroShipping) {
            $netAmount = 0;
        }

        // Calculate courier income/loss
        $courierIncomeAmount = ($isCustomerPaidZeroShipping ? $totalAmount : $totalShippingCost) - ($courierFee + $settlementFee);

        // Calculate sales amount based on profit/loss
        $baseSalesAmount = $totalAmount - $totalShippingCost;

        if ($isPaidAndDelivered) {
            if ($courierIncomeAmount > 0) {
                $salesAmount = $baseSalesAmount + $courierIncomeAmount;
            } else {
                $salesAmount = $baseSalesAmount;
            }
        } else {
            $salesAmount = $baseSalesAmount;
        }

        Log::info('Settlement amount calculations', [
            'order_code' => $order->code,
            'total_amount' => $totalAmount,
            'total_shipping_cost' => $totalShippingCost,
            'courier_fee' => $courierFee,
            'settlement_fee' => $settlementFee,
            'net_amount' => $netAmount,
            'courier_income_amount' => $courierIncomeAmount
        ]);

        // For zero-shipping partial deliveries the customer paid the courier directly
        $collectedLabel = $isCustomerPaidZeroShipping ? 'Given by customer' : 'Shipping';
        $collectedValue = $isCustomerPaidZeroShipping ? $totalAmount : $totalShippingCost;

        // Create settlement record
        $settlement = CourierSettlement::createNumbered([
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'courier_name' => $order->courier_name,
            'total_amount' => $totalAmount,
            'courier_fee' => $courierFee,
            'given_customer' => $isCustomerPaidZeroShipping ? $givenCustomer : null,
            'settlement_fee' => $settlementFee,
            'settlement_charge_percentage' => $settlementCharge,
            'net_payable' => $netAmount,
            'account_id' => $account->id,
            'account_name' => $account->name,
            'status' => 'settled',
            'settlement_date' => now(),
            'partial_payment_choice' => $partialPaymentChoice,
            'notes' => 'Settled via courier settlement system. ' . $collectedLabel . ': ৳' . number_format($collectedValue, 2) .
                ($isCustomerPaidZeroShipping ? ', Actual courier fee: ৳' . number_format($actualCourierFee, 2) : ', Courier fee: ৳' . number_format($courierFee, 2)) .
                ($isCustomerPaidZeroShipping ? ', Settlement fee: ৳' . number_format($settlementFee, 2) . ' (' . $settlementCharge . '% of Given - Actual)'
                    : ($isAdminPaidZeroShipping ? ', Settlement fee: ৳0.00 (No settlement fee - admin bears courier cost)'
                        : ', Settlement fee: ৳' . number_format($settlementFee, 2) . ' (' . $settlementCharge . '%)')) .
                ', Net amount: ৳' . number_format($netAmount, 2) .
                ($partialPaymentChoice ? ', Partial payment: ' . $partialPaymentChoice : '')
        ]);

        // Save settlement details
        foreach ($orderDetails as $detail) {
            CourierSettlementDetail::create([
                'courier_settlement_id' => $settlement->id,
                'order_detail_id' => $detail->id,
                'product_id' => $detail->product_id,
                'product_name' => $detail->product->name ?? 'Product-' . $detail->product_id,
                'quantity' => $detail->quantity,
                'price' => $detail->price,
                'selling_price' => $detail->selling_price,
                'buying_price' => $detail->buying_price,
                'profit' => $detail->profit,
                'cost_price' => $detail->cost_price,
                'tax' => $detail->tax,
                'shipping_cost' => $detail->shipping_cost,
                'payment_status' => $detail->payment_status,
                'delivery_status' => $detail->delivery_status
            ]);
        }

        // Update order with settlement information
        $order->settlement_status = 'true';
        $order->settlement_fee = $settlementFee;
        $order->courier_fee = $courierFee;
        $order->given_customer = $isCustomerPaidZeroShipping ? $givenCustomer : null;
        $order->settlement_date = now();
        $order->settlement_account_id = $account->id;
        $order->partial_payment_choice = $partialPaymentChoice;
        $order->save();

        // ==========================================
        // ACCOUNTING ENTRIES WITH STATEMENT LOGS
        // ==========================================
        $transactionDate = now()->toDateString();
        $referenceNo = $settlement->settlement_no;

        // Initialize all variables
        $accountsReceivable = null;
        $oldReceivableBalance = 0;
        $inventoryData = null;
        $cogsData = null;
        $oldAccountBalance = 0;
        $oldSalesBalance = 0;
        $salesAccount = null;
        $courierIncomeData = null;
        $badDebtData = null;
        $settlementExpenseAccount = null;
        $oldSettlementExpenseBalance = 0;
        $courierExpenseAccount = null;
        $oldCourierExpenseBalance = 0;

        // 1. DEDUCT from ACCOUNTS RECEIVABLE
        $accountsReceivable = ChartOfAccount::getAccountsReceivable();
        if (!$accountsReceivable) {
            throw new \Exception('Accounts Receivable account not found');
        }

        $arDebits = AccountStatementLog::where('order_id', $order->id)
            ->where('account_id', $accountsReceivable->id)
            ->where('transaction_type', 'order_due')
            ->where('debit', '>', 0)
            ->sum('debit');

        $arCredits = AccountStatementLog::where('order_id', $order->id)
            ->where('account_id', $accountsReceivable->id)
            ->whereIn('transaction_type', ['courier_settlement', 'order_cancelled'])
            ->where('credit', '>', 0)
            ->sum('credit');

        $netOutstandingAr = $arDebits - $arCredits;
        $arCreditAmount = min($totalAmount, $netOutstandingAr);
        $skipArCredit = ($arCreditAmount <= 0);
        $oldReceivableBalance = $accountsReceivable->current_balance;

        if (!$skipArCredit) {
            $accountsReceivable->current_balance -= $arCreditAmount;
            $accountsReceivable->save();

            $arDesc = 'Courier settlement: ' . $referenceNo . ' - Order #' . $order->code;
            if ($isPartialDelivered) {
                $arDesc .= ' (Partial delivery)';
            }

            $this->insertSettlementStatementLog(
                $accountsReceivable->id,
                $transactionDate,
                $referenceNo,
                'courier_settlement',
                0,
                $arCreditAmount,
                $arDesc,
                $settlement
            );
        }

        // Skip Inventory, COGS, and Sales entries for POS orders
        if (!$isPosAlreadyAccounted) {
            // 2. DEDUCT INVENTORY (COGS)
            $inventoryAccount = ChartOfAccount::getInventoryAccount();
            if ($inventoryAccount && $totalCOGS > 0) {
                $oldInventoryBalance = $inventoryAccount->current_balance;
                $inventoryAccount->current_balance -= $totalCOGS;
                $inventoryAccount->save();

                $this->insertSettlementStatementLog(
                    $inventoryAccount->id,
                    $transactionDate,
                    $referenceNo,
                    'courier_settlement_cogs',
                    0,
                    $totalCOGS,
                    'Inventory reduction for settlement: ' . $referenceNo . ' - Order #' . $order->code,
                    $settlement
                );

                $inventoryData = [
                    'id' => $inventoryAccount->id,
                    'name' => $inventoryAccount->name,
                    'old_balance' => number_format($oldInventoryBalance, 2),
                    'deducted_amount' => number_format($totalCOGS, 2),
                    'new_balance' => number_format($inventoryAccount->current_balance, 2)
                ];
            }

            // 3. UPDATE COST OF GOODS SOLD
            $cogsAccount = ChartOfAccount::where('type', 'expense')
                ->where('sub_type', 'cost_of_sales')
                ->where('name', 'Cost of Goods Sold')
                ->first();

            if (!$cogsAccount) {
                $cogsAccount = ChartOfAccount::where('sub_type', 'cost_of_sales')
                    ->where('status', 'active')
                    ->first();
            }

            if (!$cogsAccount) {
                $cogsAccount = ChartOfAccount::create([
                    'name' => 'Cost of Goods Sold',
                    'code' => '5-51000',
                    'type' => 'expense',
                    'sub_type' => 'cost_of_sales',
                    'status' => 'active',
                    'is_deleted' => 0,
                    'current_balance' => 0,
                    'opening_balance' => 0
                ]);
            }

            if ($cogsAccount && $totalCOGS > 0) {
                $oldCOGSBalance = $cogsAccount->current_balance;
                $cogsAccount->current_balance += $totalCOGS;
                $cogsAccount->save();

                $this->insertSettlementStatementLog(
                    $cogsAccount->id,
                    $transactionDate,
                    $referenceNo,
                    'courier_settlement_cogs',
                    $totalCOGS,
                    0,
                    'COGS for settlement: ' . $referenceNo . ' - Order #' . $order->code,
                    $settlement
                );

                $cogsData = [
                    'id' => $cogsAccount->id,
                    'name' => $cogsAccount->name,
                    'old_balance' => number_format($oldCOGSBalance, 2),
                    'added_amount' => number_format($totalCOGS, 2),
                    'new_balance' => number_format($cogsAccount->current_balance, 2)
                ];
            }
        }

        // ==========================================
        // HANDLE PARTIAL DELIVERY SETTLEMENTS
        // ==========================================
        if ($isPartialDelivered && $partialPaymentChoice == 'customer') {
            // Customer pays for partial delivery
            // Net amount after ALL deductions (courier fee + settlement fee)
            $partialNetAmount = $totalAmount - ($courierFee + $settlementFee);

            $oldAccountBalance = $account->current_balance;
            $account->current_balance += $partialNetAmount;
            $account->save();

            $this->insertSettlementStatementLog(
                $account->id,
                $transactionDate,
                $referenceNo,
                'courier_settlement',
                $partialNetAmount,
                0,
                'Partial delivery settlement (customer paid): ' . $referenceNo . ' - Order #' . $order->code .
                ' (' . $collectedLabel . ': ৳' . number_format($collectedValue, 2) .
                ' - Courier Fee: ৳' . number_format($courierFee, 2) .
                ' - Settlement Fee: ৳' . number_format($settlementFee, 2) .
                ' = Net: ৳' . number_format($partialNetAmount, 2) . ')',
                $settlement
            );

            // Profit/Loss: collected amount - (Courier Fee + Settlement Fee)
            $profitLossAmount = $totalAmount - ($courierFee + $settlementFee);

            if ($profitLossAmount > 0) {
                // Profit → Courier Income
                $courierIncomeAccount = ChartOfAccount::getCourierIncomeAccount();
                if (!$courierIncomeAccount) {
                    $courierIncomeAccount = ChartOfAccount::create([
                        'name' => 'Courier Income',
                        'code' => '4-42001',
                        'type' => 'income',
                        'sub_type' => 'income',
                        'status' => 'active',
                        'is_deleted' => 0,
                        'current_balance' => 0,
                        'opening_balance' => 0
                    ]);
                }
                $oldCourierIncomeBalance = $courierIncomeAccount->current_balance;
                $courierIncomeAccount->current_balance += $profitLossAmount;
                $courierIncomeAccount->save();

                $this->insertSettlementStatementLog(
                    $courierIncomeAccount->id,
                    $transactionDate,
                    $referenceNo,
                    'courier_settlement',
                    0,
                    $profitLossAmount,
                    'Partial delivery profit (customer paid): ' . $referenceNo . ' - Order #' . $order->code .
                    ' (' . $collectedLabel . ': ৳' . number_format($collectedValue, 2) .
                    ' - Fees: ৳' . number_format($courierFee + $settlementFee, 2) .
                    ' = Profit: ৳' . number_format($profitLossAmount, 2) . ')',
                    $settlement
                );

                $courierIncomeData = [
                    'id' => $courierIncomeAccount->id,
                    'name' => $courierIncomeAccount->name,
                    'old_balance' => number_format($oldCourierIncomeBalance, 2),
                    'added_amount' => number_format($profitLossAmount, 2),
                    'new_balance' => number_format($courierIncomeAccount->current_balance, 2),
                    'note' => 'Profit: ' . $collectedLabel . '(৳' . number_format($collectedValue, 2) . ') - Fees(৳' . number_format($courierFee + $settlementFee, 2) . ')'
                ];
            } elseif ($profitLossAmount < 0) {
                // Loss → Bad Debt Expense
                $lossAmount = abs($profitLossAmount);

                $badDebtAccount = ChartOfAccount::getBadDebtExpenseAccount();
                if (!$badDebtAccount) {
                    $badDebtAccount = ChartOfAccount::create([
                        'name' => 'Bad Debt Expense',
                        'code' => '5-51001',
                        'type' => 'expense',
                        'sub_type' => 'expense',
                        'status' => 'active',
                        'is_deleted' => 0,
                        'current_balance' => 0,
                        'opening_balance' => 0
                    ]);
                }
                $oldBadDebtBalance = $badDebtAccount->current_balance;
                $badDebtAccount->current_balance += $lossAmount;
                $badDebtAccount->save();

                $this->insertSettlementStatementLog(
                    $badDebtAccount->id,
                    $transactionDate,
                    $referenceNo,
                    'courier_settlement',
                    $lossAmount,
                    0,
                    'Loss from partial delivery (customer paid): ' . $referenceNo . ' - Order #' . $order->code .
                    ' (' . $collectedLabel . ': ৳' . number_format($collectedValue, 2) .
                    ' - Fees: ৳' . number_format($courierFee + $settlementFee, 2) .
                    ' = Loss: ৳' . number_format($lossAmount, 2) . ')',
                    $settlement
                );

                $badDebtData = [
                    'id' => $badDebtAccount->id,
                    'name' => $badDebtAccount->name,
                    'old_balance' => number_format($oldBadDebtBalance, 2),
                    'added_amount' => number_format($lossAmount, 2),
                    'new_balance' => number_format($badDebtAccount->current_balance, 2),
                    'note' => 'Loss: ' . $collectedLabel . '(৳' . number_format($collectedValue, 2) . ') - Fees(৳' . number_format($courierFee + $settlementFee, 2) . ')'
                ];
            }

        } elseif ($isPartialDelivered && $partialPaymentChoice == 'admin') {
            // Admin pays for partial delivery
            // Account gets the collected amount minus settlement fee (admin absorbs courier fee)
            // For zero-shipping nothing is collected, so no amount is credited to the account
            $partialNetAmount = $totalAmount - $settlementFee;

            if ($partialNetAmount > 0) {
                $oldAccountBalance = $account->current_balance;
                $account->current_balance += $partialNetAmount;
                $account->save();

                $this->insertSettlementStatementLog(
                    $account->id,
                    $transactionDate,
                    $referenceNo,
                    'courier_settlement',
                    $partialNetAmount,
                    0,
                    'Partial delivery settlement (admin bears courier cost): ' . $referenceNo . ' - Order #' . $order->code .
                    ' (Shipping: ৳' . number_format($totalShippingCost, 2) .
                    ' - Settlement Fee: ৳' . number_format($settlementFee, 2) .
                    ' = Net: ৳' . number_format($partialNetAmount, 2) .
                    ', Courier Fee ৳' . number_format($courierFee, 2) . ' paid by admin)',
                    $settlement
                );
            }

            // Courier fee goes to Bad Debt Expense (admin absorbs it)
            $badDebtAccount = ChartOfAccount::getBadDebtExpenseAccount();
            if (!$badDebtAccount) {
                $badDebtAccount = ChartOfAccount::create([
                    'name' => 'Bad Debt Expense',
                    'code' => '5-51001',
                    'type' => 'expense',
                    'sub_type' => 'expense',
                    'status' => 'active',
                    'is_deleted' => 0,
                    'current_balance' => 0,
                    'opening_balance' => 0
                ]);
            }
            $oldBadDebtBalance = $badDebtAccount->current_balance;
            $badDebtAccount->current_balance += $courierFee;
            $badDebtAccount->save();

            $this->insertSettlementStatementLog(
                $badDebtAccount->id,
                $transactionDate,
                $referenceNo,
                'courier_settlement',
                $courierFee,
                0,
                'Admin bears courier cost for partial delivery: ' . $referenceNo . ' - Order #' . $order->code .
                ' (Courier Fee: ৳' . number_format($courierFee, 2) . ')',
                $settlement
            );

            $badDebtData = [
                'id' => $badDebtAccount->id,
                'name' => $badDebtAccount->name,
                'old_balance' => number_format($oldBadDebtBalance, 2),
                'added_amount' => number_format($courierFee, 2),
                'new_balance' => number_format($badDebtAccount->current_balance, 2),
                'note' => 'Admin bears courier cost (Courier Fee: ৳' . number_format($courierFee, 2) . ')'
            ];

        } else {
            // Normal settlement flow (full delivery)
            if (!$isPosFullyPaid) {
                $oldAccountBalance = $account->current_balance;
                $account->current_balance += $netAmount;
                $account->save();

                $this->insertSettlementStatementLog(
                    $account->id,
                    $transactionDate,
                    $referenceNo,
                    'courier_settlement',
                    $netAmount,
                    0,
                    'Courier settlement received: ' . $referenceNo . ' - Order #' . $order->code,
                    $settlement
                );
            }

            // Settlement Expense (only for full delivery)
            $settlementExpenseAccount = ChartOfAccount::getCourierSettlementExpenseAccount();
            if (!$settlementExpenseAccount) {
                $settlementExpenseAccount = ChartOfAccount::create([
                    'name' => 'Courier Settlement Expense',
                    'code' => '5-51006',
                    'type' => 'expense',
                    'sub_type' => 'expense',
                    'status' => 'active',
                    'is_deleted' => 0,
                    'current_balance' => 0,
                    'opening_balance' => 0
                ]);
            }
            $oldSettlementExpenseBalance = $settlementExpenseAccount->current_balance;
            $settlementExpenseAccount->current_balance += $settlementFee;
            $settlementExpenseAccount->save();

            $this->insertSettlementStatementLog(
                $settlementExpenseAccount->id,
                $transactionDate,
                $referenceNo,
                'courier_settlement',
                $settlementFee,
                0,
                'Settlement fee expense: ' . $referenceNo . ' - Order #' . $order->code,
                $settlement
            );
        }

        // Courier Fee Expense (ALWAYS)
        $courierExpenseAccount = ChartOfAccount::getCourierFeeExpenseAccount();
        if (!$courierExpenseAccount) {
            $courierExpenseAccount = ChartOfAccount::create([
                'name' => 'Courier Fee Expense',
                'code' => '5-51003',
                'type' => 'expense',
                'sub_type' => 'expense',
                'status' => 'active',
                'is_deleted' => 0,
                'current_balance' => 0,
                'opening_balance' => 0
            ]);
        }
        $oldCourierExpenseBalance = $courierExpenseAccount->current_balance;
        $courierExpenseAccount->current_balance += $courierFee;
        $courierExpenseAccount->save();

        $this->insertSettlementStatementLog(
            $courierExpenseAccount->id,
            $transactionDate,
            $referenceNo,
            'courier_settlement',
            $courierFee,
            0,
            'Courier fee expense: ' . $referenceNo . ' - Order #' . $order->code,
            $settlement
        );

        // Sales Account — skip for POS paid orders
        if (!$isPosAlreadyAccounted) {
            $salesAccount = ChartOfAccount::getSalesAccount();
            if (!$salesAccount) {
                $salesAccount = ChartOfAccount::create([
                    'name' => 'Sales',
                    'code' => '4-41001',
                    'type' => 'income',
                    'sub_type' => 'sales',
                    'status' => 'active',
                    'is_deleted' => 0,
                    'current_balance' => 0,
                    'opening_balance' => 0
                ]);
            }
            $oldSalesBalance = $salesAccount->current_balance;
            $salesAccount->current_balance += $salesAmount;
            $salesAccount->save();

            $this->insertSettlementStatementLog(
                $salesAccount->id,
                $transactionDate,
                $referenceNo,
                'courier_settlement',
                0,
                $salesAmount,
                'Sales revenue from settlement: ' . $referenceNo . ' - Order #' . $order->code,
                $settlement
            );
        }

        // Courier Income or Bad Debt for full delivery (paid & delivered)
        if (!$isPartialDelivered && $isPaidAndDelivered) {
            if ($courierIncomeAmount > 0) {
                $courierIncomeAccount = ChartOfAccount::getCourierIncomeAccount();
                if (!$courierIncomeAccount) {
                    $courierIncomeAccount = ChartOfAccount::create([
                        'name' => 'Courier Income',
                        'code' => '4-42001',
                        'type' => 'income',
                        'sub_type' => 'income',
                        'status' => 'active',
                        'is_deleted' => 0,
                        'current_balance' => 0,
                        'opening_balance' => 0
                    ]);
                }
                $oldCourierIncomeBalance = $courierIncomeAccount->current_balance;
                $courierIncomeAccount->current_balance += $courierIncomeAmount;
                $courierIncomeAccount->save();

                $this->insertSettlementStatementLog(
                    $courierIncomeAccount->id,
                    $transactionDate,
                    $referenceNo,
                    'courier_settlement',
                    0,
                    $courierIncomeAmount,
                    'Courier income from settlement: ' . $referenceNo . ' - Order #' . $order->code,
                    $settlement
                );

                $courierIncomeData = [
                    'id' => $courierIncomeAccount->id,
                    'name' => $courierIncomeAccount->name,
                    'old_balance' => number_format($oldCourierIncomeBalance, 2),
                    'added_amount' => number_format($courierIncomeAmount, 2),
                    'new_balance' => number_format($courierIncomeAccount->current_balance, 2)
                ];
            } elseif ($courierIncomeAmount < 0) {
                $badDebtAccount = ChartOfAccount::getBadDebtExpenseAccount();
                if (!$badDebtAccount) {
                    $badDebtAccount = ChartOfAccount::create([
                        'name' => 'Bad Debt Expense',
                        'code' => '5-51001',
                        'type' => 'expense',
                        'sub_type' => 'expense',
                        'status' => 'active',
                        'is_deleted' => 0,
                        'current_balance' => 0,
                        'opening_balance' => 0
                    ]);
                }
                $oldBadDebtBalance = $badDebtAccount->current_balance;
                $lossAmount = abs($courierIncomeAmount);
                $badDebtAccount->current_balance += $lossAmount;
                $badDebtAccount->save();

                $this->insertSettlementStatementLog(
                    $badDebtAccount->id,
                    $transactionDate,
                    $referenceNo,
                    'courier_settlement',
                    $lossAmount,
                    0,
                    'Loss from courier settlement: ' . $referenceNo . ' - Order #' . $order->code . ' (Shipping: ৳' . number_format($totalShippingCost, 2) . ' - Fees: ৳' . number_format($courierFee + $settlementFee, 2) . ')',
                    $settlement
                );

                $badDebtData = [
                    'id' => $badDebtAccount->id,
                    'name' => $badDebtAccount->name,
                    'old_balance' => number_format($oldBadDebtBalance, 2),
                    'added_amount' => number_format($lossAmount, 2),
                    'new_balance' => number_format($badDebtAccount->current_balance, 2),
                    'note' => 'Loss from courier settlement (Shipping cost ৳' . number_format($totalShippingCost, 2) . ' - Total fees ৳' . number_format($courierFee + $settlementFee, 2) . ')'
                ];
            }
        }

        DB::commit();

        // Prepare response
        $responseData = [
            'success' => true,
            'message' => 'Order settled successfully',
            'settlement_no' => $settlement->settlement_no,
            'calculation' => [
                'total_amount' => number_format($totalAmount, 2),
                'total_shipping_cost' => number_format($totalShippingCost, 2),
                'total_cogs' => number_format($totalCOGS, 2),
                'courier_fee' => number_format($courierFee, 2),
                'settlement_charge_percentage' => $settlementCharge . '%',
                'settlement_fee' => number_format($settlementFee, 2),
                'net_amount' => number_format($netAmount, 2),
                'courier_income_amount' => number_format($courierIncomeAmount, 2),
                'base_sales_amount' => number_format($baseSalesAmount, 2),
                'final_sales_amount' => number_format($salesAmount, 2),
                'is_paid_and_delivered' => $isPaidAndDelivered,
                'is_partial_delivered' => $isPartialDelivered,
                'partial_payment_choice' => $partialPaymentChoice,
                'given_customer' => $isCustomerPaidZeroShipping ? number_format($givenCustomer, 2) : null,
                'actual_courier_fee' => $isCustomerPaidZeroShipping ? number_format($actualCourierFee, 2) : null
            ],
            'inventory_reduction' => $inventoryReductionDetails,
            'account_updates' => [
                'accounts_receivable' => [
                    'id' => $accountsReceivable->id ?? null,
                    'name' => ($accountsReceivable->name ?? 'N/A'),
                    'old_balance' => number_format($oldReceivableBalance ?? 0, 2),
                    'deducted_amount' => $skipArCredit ? 'Skipped (No AR outstanding)' : number_format($arCreditAmount, 2),
                    'new_balance' => number_format(($accountsReceivable->current_balance ?? 0), 2)
                ],
                'selected_account' => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'old_balance' => number_format($oldAccountBalance ?? 0, 2),
                    'added_amount' => $isPosFullyPaid ? 'Skipped (POS fully paid)' : number_format($netAmount, 2),
                    'new_balance' => number_format($account->current_balance, 2)
                ],
                'sales_account' => [
                    'id' => $salesAccount->id ?? null,
                    'name' => ($salesAccount->name ?? 'N/A'),
                    'old_balance' => number_format($oldSalesBalance ?? 0, 2),
                    'added_amount' => $isPosAlreadyAccounted ? 'Skipped (POS)' : number_format($salesAmount, 2),
                    'new_balance' => number_format(($salesAccount->current_balance ?? 0), 2)
                ],
                'courier_fee_expense' => [
                    'id' => $courierExpenseAccount->id,
                    'name' => $courierExpenseAccount->name,
                    'old_balance' => number_format($oldCourierExpenseBalance, 2),
                    'added_amount' => number_format($courierFee, 2),
                    'new_balance' => number_format($courierExpenseAccount->current_balance, 2)
                ]
            ]
        ];

        // Add inventory data to response if available
        if ($inventoryData) {
            $responseData['account_updates']['inventory'] = $inventoryData;
        }

        // Add COGS data to response if available
        if ($cogsData) {
            $responseData['account_updates']['cost_of_goods_sold'] = $cogsData;
        }

        // Add settlement expense for full delivery
        if (!$isPartialDelivered && isset($settlementExpenseAccount)) {
            $responseData['account_updates']['courier_settlement_expense'] = [
                'id' => $settlementExpenseAccount->id,
                'name' => $settlementExpenseAccount->name,
                'old_balance' => number_format($oldSettlementExpenseBalance, 2),
                'added_amount' => number_format($settlementFee, 2),
                'new_balance' => number_format($settlementExpenseAccount->current_balance, 2)
            ];
        }

        if ($courierIncomeData) {
            $responseData['account_updates']['courier_income'] = $courierIncomeData;
        }
        if ($badDebtData) {
            $responseData['account_updates']['bad_debt_expense'] = $badDebtData;
        }

        return response()->json($responseData);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Courier settlement error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to settle order: ' . $e->getMessage()
        ]);
    }
}

    /**
     * Reverse (unsettle) a courier settlement.
     * Reverses all accounting entries, deletes statement logs, resets order, deletes settlement record.
     */
    public function unsettle(Request $request)
    {
        $request->validate([
            'settlement_id' => 'required|exists:courier_settlements,id',
        ]);

        try {
            DB::beginTransaction();

            // document first: a repeated/simultaneous unsettle waits here and then finds the settlement gone (it is deleted below), so the reversal is applied once
            $settlement = CourierSettlement::with('order')->lockForUpdate()->findOrFail($request->settlement_id);
            $order = $settlement->order;

            if (!$order) {
                DB::rollBack(); // refusal: leave the transaction (and release its locks) before answering
                return response()->json([
                    'success' => false,
                    'message' => 'Associated order not found'
                ]);
            }

            // 1. Reverse all accounting entries from statement logs linked to this settlement
            $statementLogs = AccountStatementLog::where('source_type', CourierSettlement::class)
                ->where('source_id', $settlement->id)
                ->get();

            foreach ($statementLogs as $log) {
                $account = ChartOfAccount::find($log->account_id);
                if (!$account) continue;

                // Reverse the entry: if settlement debited the account, we credit (subtract) and vice versa
                if ($log->debit > 0) {
                    // Original entry debited (increased balance) → reverse by subtracting
                    $account->current_balance -= $log->debit;
                }
                if ($log->credit > 0) {
                    // Original entry credited (decreased balance) → reverse by adding
                    $account->current_balance += $log->credit;
                }
                $account->save();
            }

            // 2. Delete all statement logs for this settlement
            AccountStatementLog::where('source_type', CourierSettlement::class)
                ->where('source_id', $settlement->id)
                ->delete();

            // 3. Delete settlement detail records
            CourierSettlementDetail::where('courier_settlement_id', $settlement->id)->delete();

            // 4. Reset order settlement fields
            $order->settlement_status = 'false';
            $order->settlement_fee = null;
            $order->courier_fee = null;
            $order->given_customer = null;
            $order->settlement_date = null;
            $order->settlement_account_id = null;
            $order->partial_payment_choice = null;
            $order->save();

            // 5. Delete the settlement record
            $settlement->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Settlement reversed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Courier unsettle error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reverse settlement: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Insert statement log for settlement transactions
     */
    private function insertSettlementStatementLog(
        $accountId,
        $date,
        $referenceNo,
        $type,
        $debit,
        $credit,
        $desc,
        $settlement
    ) {
        try {
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
                'source_id'        => $settlement->id,
                'source_type'      => get_class($settlement),
                'voucher_id'       => null,
                'order_id'         => $settlement->order_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to insert settlement statement log: ' . $e->getMessage());
            throw $e;
        }
    }

    // Method to get settlement report
    public function report()
    {
        $settlements = CourierSettlement::with(['order', 'user', 'account'])
            ->where('status', 'settled')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $totalSettled = CourierSettlement::where('status', 'settled')->sum('settlement_fee');
        $totalCourierFee = CourierSettlement::where('status', 'settled')->sum('courier_fee');
        $totalNetPayable = CourierSettlement::where('status', 'settled')->sum('net_payable');

        // Get expense account balances
        $courierExpense = ChartOfAccount::where('name', 'Courier Fee Expense')->first();
        $settlementExpense = ChartOfAccount::where('name', 'Courier Settlement Expense')->first();

        return view('backend.courier_settlement.report', compact(
            'settlements',
            'totalSettled',
            'totalCourierFee',
            'totalNetPayable',
            'courierExpense',
            'settlementExpense'
        ));
    }

    // Method to get settlement details
    public function getSettlementDetails($id)
    {
        $settlement = CourierSettlement::with(['settlementDetails', 'order', 'user', 'account'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $settlement
        ]);
    }
}
