<?php

namespace App\Http\Controllers;

use App\Models\AccountStatementLog;
use App\Models\BalanceTransfer;
use App\Models\TransactionMethod;
use App\Models\ChartOfAccount;
use App\Models\SupplierPayment;
use App\Models\CustomerPayment;
use App\Models\BankTransaction;
use App\Models\CostCenter;
use App\Models\CourierSettlement;
use App\Models\Voucher;
use App\Models\ExpensePayment;
use App\Models\Income;
use App\Models\Supplier;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\ExpensePaymentDetail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{

    public function settlementDetails($id)
    {
        $settlement = CourierSettlement::with(['order.orderDetails.product', 'user', 'account'])->findOrFail($id);

        $order = $settlement->order;

        if (!$order) {
            return '<div class="alert alert-warning">Order not found for this settlement.</div>';
        }

        return view('backend.accounts.customer-payments.order_settlement_details', compact('settlement', 'order'));
    }

    /**
     * Export accounts data
     */
    public function export(Request $request)
    {
        $tab = $request->get('tab', 'dashboard');

        // Get data based on tab
        switch ($tab) {
            case 'dashboard':
                $data = [
                    'total_assets' => ChartOfAccount::where('type', 'asset')->sum('current_balance'),
                    'total_liabilities' => ChartOfAccount::where('type', 'liability')->sum('current_balance'),
                    'total_income' => Income::sum('total_amount'),
                    'total_expense' => ExpensePayment::sum('total_amount'),
                    'net_income' => Income::sum('total_amount') - ExpensePayment::sum('total_amount'),
                    'export_date' => now()->format('Y-m-d H:i:s'),
                ];
                break;
            default:
                $data = ['message' => 'Export data not available for this tab'];
        }

        // Return as JSON or CSV
        if ($request->get('format') === 'csv') {
            // Return as CSV download
            $filename = "accounts_export_{$tab}_" . now()->format('Ymd_His') . ".csv";
            $handle = fopen('php://temp', 'w');

            // Add headers
            fputcsv($handle, array_keys($data));

            // Add data
            fputcsv($handle, $data);

            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        // Return as JSON
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Export data retrieved successfully'
        ]);
    }

    public function accountStatementFilter(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:chart_of_accounts,id',
            'date_range' => 'nullable|string',
        ]);

        $accountId = $request->account_id;
        $dates = $request->date_range ? explode(' to ', $request->date_range) : [];

        $startDate = null;
        $endDate = null;
        if (count($dates) == 2) {
            try {
                $startDate = \Carbon\Carbon::createFromFormat('d-m-Y', trim($dates[0]))->startOfDay();
                $endDate = \Carbon\Carbon::createFromFormat('d-m-Y', trim($dates[1]))->endOfDay();
            } catch (\Exception $e) {
            }
        }

        // Determine account type for In/Out display logic
        $selAccount = ChartOfAccount::find($accountId);
        $accountType = $selAccount ? $selAccount->type : 'asset';

        // Previous balance
        $previousLog = AccountStatementLog::where('account_id', $accountId)
            ->when($startDate, fn($q) => $q->where('transaction_date', '<', $startDate))
            ->orderBy('id', 'desc')
            ->first();
        $previousBalance = $previousLog ? $previousLog->balance : 0;

        // Logs within range
        $logs = AccountStatementLog::where('account_id', $accountId)
            ->when($startDate, fn($q) => $q->where('transaction_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('transaction_date', '<=', $endDate))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        // Build HTML rows directly
        $runningBalance = $previousBalance;
        $html = '';
        foreach ($logs as $log) {
            if ($accountType === 'income' || $accountType === 'liability') {
                $runningBalance = $runningBalance + $log->credit - $log->debit;
            } else {
                $runningBalance = $runningBalance + $log->debit - $log->credit;
            }
            if ($accountType === 'income' || $accountType === 'expense' || $accountType === 'liability') {
                $displayIn = $log->credit > 0 ? number_format($log->credit, 2) : '0.00';
                $displayOut = $log->debit > 0 ? number_format($log->debit, 2) : '0.00';
            } else {
                $displayIn = $log->debit > 0 ? number_format($log->debit, 2) : '0.00';
                $displayOut = $log->credit > 0 ? number_format($log->credit, 2) : '0.00';
            }
            $html .= '<tr>';
            $html .= '<td>' . optional($log->transaction_date)->format('d M Y') . '</td>';
            $html .= '<td>' . $log->reference_no . '</td>';
            $html .= '<td>' . $log->description . '</td>';
            $html .= '<td class="text-right text-success">' . $displayIn . '</td>';
            $html .= '<td class="text-right text-danger">' . $displayOut . '</td>';
            $html .= '<td class="text-right font-weight-bold">' . number_format($runningBalance, 2) . '</td>';
            $html .= '</tr>';
        }

        return response()->json([
            'previous_balance' => number_format($previousBalance, 2),
            'html'             => $html,
            'is_income'        => $accountType === 'income',
        ]);
    }
    public function loadTab(Request $request)
    {
        $tab = $request->get('tab', 'dashboard');

        try {
            $html = '';

            switch ($tab) {
                case 'dashboard':
                    // Load dashboard data
                    $assetAccounts = ChartOfAccount::where('type', 'asset')->where('status', 'active')->get();
                    $liabilityAccounts = ChartOfAccount::where('type', 'liability')->where('status', 'active')->get();
                    $incomeAccounts = ChartOfAccount::where('type', 'income')->where('status', 'active')->get();
                    $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('status', 'active')->get();

                    $totalAssets = $assetAccounts->sum('current_balance');
                    $totalLiabilities = $liabilityAccounts->sum('current_balance');

                    $cashBankBalance = ChartOfAccount::whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
                        ->where('status', 'active')
                        ->sum('current_balance');

                    $currentAssets = ChartOfAccount::where('type', 'asset')
                        ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank', 'receivable'])
                        ->where('status', 'active')
                        ->get();
                    $currentAssetsTotal = $currentAssets->sum('current_balance');

                    $bankAccounts = ChartOfAccount::where('type', 'asset')
                        ->whereIn('sub_type', ['bank', 'mobile_bank'])
                        ->where('status', 'active')
                        ->get();
                    $bankCashTotal = $bankAccounts->sum('current_balance');

                    $fixedAssets = ChartOfAccount::where('type', 'asset')
                        ->whereIn('sub_type', ['inventory', 'fixed_asset'])
                        ->where('status', 'active')
                        ->get();
                    $fixedAssetsTotal = $fixedAssets->sum('current_balance');

                    $currentLiabilities = ChartOfAccount::where('type', 'liability')
                        ->whereIn('sub_type', ['payable', 'tax'])
                        ->where('status', 'active')
                        ->get();
                    $currentLiabilitiesTotal = $currentLiabilities->sum('current_balance');

                    $supplierPayments = SupplierPayment::with(['supplier', 'paymentAccount'])
                        ->latest()
                        ->take(10)
                        ->get();

                    $customerPayments = CustomerPayment::with(['customer', 'paymentAccount', 'orders'])
                        ->latest()
                        ->take(10)
                        ->get();

                    $html = view('backend.accounts.dashboard', compact(
                        'assetAccounts',
                        'liabilityAccounts',
                        'incomeAccounts',
                        'expenseAccounts',
                        'totalAssets',
                        'totalLiabilities',
                        'cashBankBalance',
                        'currentAssets',
                        'currentAssetsTotal',
                        'bankAccounts',
                        'bankCashTotal',
                        'fixedAssets',
                        'fixedAssetsTotal',
                        'currentLiabilities',
                        'currentLiabilitiesTotal',
                        'supplierPayments',
                        'customerPayments'
                    ))->render();
                    break;

                case 'chart-of-accounts':
                    $assetAccounts = ChartOfAccount::where('type', 'asset')->where('status', 'active')->get();
                    $liabilityAccounts = ChartOfAccount::where('type', 'liability')->where('status', 'active')->get();
                    $incomeAccounts = ChartOfAccount::where('type', 'income')->where('status', 'active')->get();
                    $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('status', 'active')->get();

                    $html = view('backend.accounts.chart_of_accounts', compact(
                        'assetAccounts',
                        'liabilityAccounts',
                        'incomeAccounts',
                        'expenseAccounts'
                    ))->render();
                    break;

                case 'account-statement':
                    $html = view('backend.accounts.account_statement')->render();
                    break;

                case 'payment-methods':
                    $methods = TransactionMethod::all();
                    $assetAccounts = ChartOfAccount::where('type', 'asset')->where('status', 'active')->get();
                    $bankAccounts = ChartOfAccount::where('type', 'asset')
                        ->where('sub_type', 'bank')
                        ->where('status', 'active')
                        ->get();

                    $html = view('backend.accounts.payment_methods.index', compact('methods', 'assetAccounts', 'bankAccounts'))->render();
                    break;

                case 'supplier-payments':
                    $supplierPayments = SupplierPayment::with(['supplier', 'paymentAccount', 'transactionMethod'])
                        ->latest()
                        ->paginate(20);
                    $suppliers = Supplier::all();
                    $paymentAccounts = ChartOfAccount::whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
                        ->where('status', 'active')
                        ->get();

                    $html = view('backend.accounts.supplier_payments', compact('supplierPayments', 'suppliers', 'paymentAccounts'))->render();
                    break;

                case 'customer-payments':
                    $customerPayments = CustomerPayment::with(['customer', 'paymentAccount', 'transactionMethod', 'orders'])
                        ->latest()
                        ->paginate(20);
                    $customers = User::whereHas('orders', function ($q) {
                        $q->where('payment_status', 'paid');
                    })->get();
                    $paymentAccounts = ChartOfAccount::whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
                        ->where('status', 'active')
                        ->get();

                    $html = view('backend.accounts.customer_payments', compact('customerPayments', 'customers', 'paymentAccounts'))->render();
                    break;

                case 'vouchers':
                    $vouchers = Voucher::with(['creator', 'details.account'])
                        ->latest()
                        ->paginate(20);
                    $html = view('backend.accounts.vouchers.index', compact('vouchers'))->render();
                    break;

                case 'withdraw-deposit':
                    $transactions = BalanceTransfer::with(['fromAccount', 'toAccount', 'creator'])
                        ->latest()
                        ->paginate(50);
                    break;

                case 'expenses':
                    $expensePayments = ExpensePayment::with(['paymentAccount', 'transactionMethod'])
                        ->latest()
                        ->paginate(20);
                    $paymentAccounts = ChartOfAccount::whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
                        ->where('status', 'active')
                        ->get();

                    $html = view('backend.accounts.expense_payments', compact('expensePayments', 'paymentAccounts'))->render();
                    break;

                case 'incomes':
                    $incomes = Income::with(['receivedAccount', 'costCenter', 'creator', 'details.incomeAccount'])
                        ->latest()
                        ->paginate(20);

                    $receivedAccounts = ChartOfAccount::where('status', 'active')
                        ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
                        ->where('type', 'asset')
                        ->get();

                    $html = view('backend.accounts.income.index', compact('incomes', 'receivedAccounts'))->render();
                    break;

                default:
                    $html = view('backend.accounts.dashboard')->render();
            }

            return response()->json([
                'success' => true,
                'html' => $html
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'dashboard');

        // Common data needed across multiple tabs
        $courierSettlements = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        $methods = TransactionMethod::all();
        $accounts = ChartOfAccount::where('status', 'active')->get();
        $costCenters = CostCenter::where('status', 'active')->get();
        $paymentAccounts = ChartOfAccount::whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->get();
        $mobileBankAccounts = ChartOfAccount::where('sub_type', 'mobile_bank')
            ->where('status', 'active')
            ->get();

        // Initialize variables with default values
        $assetAccounts = collect();
        $liabilityAccounts = collect();
        $incomeAccounts = collect();
        $expenseAccounts = collect();
        $totalAssets = 0;
        $totalLiabilities = 0;
        $cashBankBalance = 0;
        $currentAssets = collect();
        $currentAssetsTotal = 0;
        $bankAccounts = collect();
        $bankCashTotal = 0;
        $fixedAssets = collect();
        $fixedAssetsTotal = 0;
        $currentLiabilities = collect();
        $currentLiabilitiesTotal = 0;
        $supplierPayments = collect();
        $suppliers = collect();
        $customerPayments = collect();
        $customers = collect();
        $vouchers = collect();
        $expensePayments = collect();
        $incomes = collect();
        $transactions = collect();
        $bankTransactions = collect();

        // Dashboard specific variables
        $totalIncome = 0;
        $totalExpense = 0;
        $netIncome = 0;
        $profitMargin = 0;
        $incomingRevenue = 0;
        $operatingExpenses = 0;
        $netSurplus = 0;
        $revenuePercentage = 0;
        $expensePercentage = 0;
        $totalAccounts = 0;
        $reconciledAccounts = 0;
        $reconciledPercentage = 0;
        $pendingApprovals = 0;
        $pendingPercentage = 0;
        $overdueInvoices = 0;
        $overduePercentage = 0;
        $monthlyIncome = [];
        $monthlyExpense = [];
        $monthLabels = [];
        $recentTransactions = collect();
        $assetLabels = [];
        $assetValues = [];
        $expenseLabels = [];
        $expenseValues = [];
        $assetData = collect(); // Initialize assetData
        $totalAssetsGrowth = 4.2;
        $totalLiabilitiesGrowth = -1.8;
        $netIncomeGrowth = 12.5;
        $totalExpensesGrowth = 8.2;
        $profitMarginGrowth = 3.0;

        $totalSupplierDueBalance = Supplier::sum('due_amount') ?? 0;
        // $totalMonthlySale = Supplier::sum('due_amount') ?? 0;
        $totalMonthlySale = Order::where('delivery_status', 'delivered')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('grand_total');
        $totalSalesAmount = Order::sum('grand_total');

        $currentYear = date('Y');

        // Total sales from orders in current year
        $totalSiteSales = (float) Order::whereYear('created_at', $currentYear)
            ->where('payment_status', 'paid')
            ->sum('grand_total');

        // Total buying cost from order_details in current year
        $totalBuyingCost = (float) DB::table('order_details')
            ->join('orders', 'order_details.order_id', '=', 'orders.id')
            ->whereYear('orders.created_at', $currentYear)
            ->where('orders.payment_status', 'paid')
            ->sum('order_details.buying_price');

        // Yearly expenses using AccountStatementLog debits to expense accounts
        $totalYearExpenses = (float) AccountStatementLog::whereHas('account', function ($q) {
            $q->where('type', 'expense')->where('sub_type', 'expense');
        })
            ->whereYear('transaction_date', $currentYear)
            ->where('debit', '>', 0)
            ->sum('debit');

        // Profit = Sales - Cost of Goods Sold
        $totalProfit = $totalSiteSales - $totalBuyingCost;

        // Current Year Earnings = Profit - Expenses
        $totalCurrentYearEarnings = $totalProfit - $totalYearExpenses;

        // Account statement specific (fix: initialize these here)
        $selectedAccount = null;
        $selectedDateRange = null;
        $previousBalance = 0;
        $rowsHtml = '';
        $isIncomeAccount = false;
        $stockValue = 0;
        $todaySales = 0;
        $receivableAmount = 0;
        $payableAmount = 0;
        $currentStockvalue = 0;
        $pettycashValue = 0;

        $currentStockvalue = DB::table('purchase_products')
            ->where('remaining_qty', '>', 0)
            ->sum(DB::raw('remaining_qty * buying_unit_cost'));

        // Load data based on active tab
        switch ($activeTab) {
            case 'dashboard':
                // Get current year and month
                $currentYear = now()->year;
                $currentMonth = now()->month;

                // Get all accounts with balances
                $assetAccounts = ChartOfAccount::where('type', 'asset')
                    ->where('status', 'active')
                    ->get();

                $liabilityAccounts = ChartOfAccount::where('type', 'liability')
                    ->where('status', 'active')
                    ->get();

                $incomeAccounts = ChartOfAccount::where('type', 'income')
                    ->where('status', 'active')
                    ->get();

                $expenseAccounts = ChartOfAccount::where('type', 'expense')
                    ->where('status', 'active')
                    ->get();

                // Calculate totals from actual account balances
                $totalAssets = $assetAccounts->sum('current_balance');
                $totalLiabilities = $liabilityAccounts->sum('current_balance');

                // Cash & Bank Balance
                $cashBankBalance = ChartOfAccount::whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
                    ->where('status', 'active')
                    ->sum('current_balance');

                // ============ MAIN FINANCIAL CALCULATIONS ============

                // Total sales (only completed orders - delivered AND paid)
                $SiteSales = (float) Order::where('payment_status', 'paid')
                    ->sum('grand_total');

                // Cost of goods sold (only for completed orders)
                $BuyingCost = (float) DB::table('order_details')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->where('orders.payment_status', 'paid')
                    ->sum('order_details.buying_price');

                // Total expenses from chart of accounts (type=expense, sub_type=expense only)
                $totalExpense = (float) DB::table('chart_of_accounts')
                    ->where('type', 'expense')
                    ->where('sub_type', 'expense')
                    ->sum('current_balance');

                // Total Income (aligned with sales)
                $totalIncome = $SiteSales;

                // Profit = Sales - Cost of Goods Sold
                $Profit = $SiteSales - $BuyingCost;

                // Net Income = Profit - Expenses
                $netIncome = $Profit - $totalExpense;

                // Profit Margin = (Net Income / Total Sales) * 100
                $profitMargin = $SiteSales > 0 ? ($netIncome / $SiteSales) * 100 : 0;

                // ============ BANK ACCOUNTS ============

                $bankAccounts = ChartOfAccount::where('type', 'asset')
                    ->where(function ($query) {
                        $query->where('sub_type', 'bank')
                            ->orWhere('sub_type', 'mobile_bank');
                    })
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get();

                $bankCashTotal = $bankAccounts->sum('current_balance');

                // ============ MONTHLY CHART DATA (LAST 6 MONTHS) ============

                $monthlyIncome = [];
                $monthlyExpense = [];
                $monthLabels = [];

                // Last 5 completed months (not including current month)
                for ($i = 5; $i >= 1; $i--) {
                    $month = now()->subMonths($i);
                    $monthLabels[] = $month->format('M Y');

                    // Monthly Sales Value
                    $monthSalesValue = (float) Order::whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->where('delivery_status', 'delivered')
                        ->where('payment_status', 'paid')
                        ->sum('grand_total') ?? 0;

                    // Monthly COGS (buying cost) for the same orders
                    $monthBuyingCost = (float) DB::table('order_details')
                        ->join('orders', 'order_details.order_id', '=', 'orders.id')
                        ->whereYear('orders.created_at', $month->year)
                        ->whereMonth('orders.created_at', $month->month)
                        ->where('orders.delivery_status', 'delivered')
                        ->where('orders.payment_status', 'paid')
                        ->sum('order_details.buying_price') ?? 0;

                    // Monthly Expense Value (using AccountStatementLog debits to expense accounts)
                    $monthExpenseValue = (float) AccountStatementLog::whereHas('account', function ($q) {
                        $q->where('type', 'expense')->where('sub_type', 'expense');
                    })
                        ->whereYear('transaction_date', $month->year)
                        ->whereMonth('transaction_date', $month->month)
                        ->where('debit', '>', 0)
                        ->sum('debit') ?? 0;

                    $monthlyIncome[] = floatval($monthSalesValue - $monthBuyingCost);
                    $monthlyExpense[] = floatval($monthExpenseValue);
                }

                // Current month (partial data)
                $currentMonthObj = now();
                $monthLabels[] = $currentMonthObj->format('M Y');

                $currentMonthSales = (float) Order::whereYear('created_at', $currentMonthObj->year)
                    ->whereMonth('created_at', $currentMonthObj->month)
                    ->where('delivery_status', 'delivered')
                    ->where('payment_status', 'paid')
                    ->sum('grand_total') ?? 0;

                $currentMonthBuyingCost = (float) DB::table('order_details')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->whereYear('orders.created_at', $currentMonthObj->year)
                    ->whereMonth('orders.created_at', $currentMonthObj->month)
                    ->where('orders.delivery_status', 'delivered')
                    ->where('orders.payment_status', 'paid')
                    ->sum('order_details.buying_price') ?? 0;

                $currentMonthExpense = (float) AccountStatementLog::whereHas('account', function ($q) {
                    $q->where('type', 'expense')->where('sub_type', 'expense');
                })
                    ->whereYear('transaction_date', $currentMonthObj->year)
                    ->whereMonth('transaction_date', $currentMonthObj->month)
                    ->where('debit', '>', 0)
                    ->sum('debit') ?? 0;

                $monthlyIncome[] = floatval($currentMonthSales - $currentMonthBuyingCost);
                $monthlyExpense[] = floatval($currentMonthExpense);

                // ============ RECENT TRANSACTIONS ============

                $recentTransactions = BalanceTransfer::with(['fromAccount', 'toAccount'])
                    ->latest()
                    ->take(5)
                    ->get();

                // ============ FINANCIAL INSIGHTS ============

                $incomingRevenue = $totalIncome;
                $operatingExpenses = $totalExpense;
                $netSurplus = $netIncome;

                // Percentages for progress bars
                $totalIncomeExpense = $totalIncome + $totalExpense;
                $revenuePercentage = $totalIncomeExpense > 0 ? ($totalIncome / $totalIncomeExpense) * 100 : 0;
                $expensePercentage = $totalIncomeExpense > 0 ? ($totalExpense / $totalIncomeExpense) * 100 : 0;

                // ============ ACCOUNT HEALTH METRICS ============

                $totalAccounts = ChartOfAccount::where('status', 'active')->count();
                $reconciledAccounts = ChartOfAccount::where('status', 'active')
                    ->where('current_balance', '>', 0)
                    ->count();
                $reconciledPercentage = $totalAccounts > 0 ? ($reconciledAccounts / $totalAccounts) * 100 : 0;

                $pendingApprovals = BalanceTransfer::where('status', 'pending')->count();
                $pendingPercentage = $totalAccounts > 0 ? ($pendingApprovals / $totalAccounts) * 100 : 0;

                $overdueInvoices = 0; // Implement based on your business logic
                $overduePercentage = 0;

                // ============ ASSET BREAKDOWN (PIE CHART) ============

                $assetData = ChartOfAccount::where('type', 'asset')
                    ->where('status', 'active')
                    ->where('current_balance', '>', 0)
                    ->select('sub_type', \DB::raw('SUM(current_balance) as total'))
                    ->groupBy('sub_type')
                    ->orderBy('total', 'desc')
                    ->get();

                $assetLabels = $assetData->pluck('sub_type')->map(function ($item) {
                    $labels = [
                        'bank' => 'Bank',
                        'cash' => 'Cash',
                        'mobile_bank' => 'Mobile Banking',
                        'receivable' => 'Receivable',
                        'inventory' => 'Inventory',
                        'fixed_asset' => 'Fixed Assets'
                    ];
                    return $labels[$item] ?? ucfirst(str_replace('_', ' ', $item));
                })->toArray();

                $assetValues = $assetData->pluck('total')->map(function ($value) {
                    return floatval($value);
                })->toArray();

                // If no asset data, provide defaults
                if (empty($assetValues)) {
                    $assetLabels = ['No Assets'];
                    $assetValues = [0];
                }

                // ============ EXPENSE BREAKDOWN (PIE CHART) ============

                $expenseData = ExpensePaymentDetail::with('expenseAccount')
                    ->select('expense_account_id', \DB::raw('SUM(amount) as total'))
                    ->groupBy('expense_account_id')
                    ->orderBy('total', 'desc')
                    ->limit(4)
                    ->get();

                // If no expense details found, use expense payments as fallback
                if ($expenseData->isEmpty()) {
                    $expenseData = ExpensePayment::join('expense_payment_details', 'expense_payments.id', '=', 'expense_payment_details.expense_payment_id')
                        ->select('expense_payment_details.expense_account_id', \DB::raw('SUM(expense_payment_details.amount) as total'))
                        ->groupBy('expense_payment_details.expense_account_id')
                        ->orderBy('total', 'desc')
                        ->limit(4)
                        ->get();
                }

                $expenseLabels = $expenseData->map(function ($item) {
                    $account = ChartOfAccount::find($item->expense_account_id);
                    return $account ? $account->name : 'Other';
                })->toArray();

                $expenseValues = $expenseData->pluck('total')->map(function ($value) {
                    return floatval($value);
                })->toArray();

                // If no expense data, provide defaults
                if (empty($expenseValues)) {
                    $expenseLabels = ['No Expenses'];
                    $expenseValues = [0];
                }

                // ============ GROWTH PERCENTAGES ============

                $lastMonth = now()->subMonth();

                // Previous month assets
                $prevMonthTotalAssets = ChartOfAccount::where('type', 'asset')
                    ->where('status', 'active')
                    ->sum('current_balance');
                $totalAssetsGrowth = $prevMonthTotalAssets > 0 ? (($totalAssets - $prevMonthTotalAssets) / $prevMonthTotalAssets) * 100 : 0;

                // Previous month liabilities
                $prevMonthLiabilities = ChartOfAccount::where('type', 'liability')
                    ->where('status', 'active')
                    ->sum('current_balance');
                $totalLiabilitiesGrowth = $prevMonthLiabilities > 0 ? (($totalLiabilities - $prevMonthLiabilities) / $prevMonthLiabilities) * 100 : 0;

                // Previous month income (using consistent filtering)
                $prevMonthIncome = Order::whereYear('created_at', $lastMonth->year)
                    ->whereMonth('created_at', $lastMonth->month)
                    ->where('delivery_status', 'delivered')
                    ->where('payment_status', 'paid')
                    ->sum('grand_total') ?? 0;
                $netIncomeGrowth = $prevMonthIncome > 0 ? (($totalIncome - $prevMonthIncome) / $prevMonthIncome) * 100 : 0;

                // Previous month expense
                $prevMonthExpense = (float) AccountStatementLog::whereHas('account', function ($q) {
                    $q->where('type', 'expense')->where('sub_type', 'expense');
                })
                    ->whereYear('transaction_date', $lastMonth->year)
                    ->whereMonth('transaction_date', $lastMonth->month)
                    ->where('debit', '>', 0)
                    ->sum('debit') ?? 0;
                $totalExpensesGrowth = $prevMonthExpense > 0 ? (($totalExpense - $prevMonthExpense) / $prevMonthExpense) * 100 : 0;

                // Previous month profit margin
                $prevProfitMargin = $prevMonthIncome > 0 ? (($prevMonthIncome - $prevMonthExpense) / $prevMonthIncome) * 100 : 0;
                $profitMarginGrowth = $prevProfitMargin > 0 ? (($profitMargin - $prevProfitMargin) / $prevProfitMargin) * 100 : 0;

                // ============ ADDITIONAL METRICS ============

                // Supplier Due Balance
                $totalSupplierDueBalance = \App\Models\Supplier::sum('due_amount') ?? 0;
                $totalMonthlySale = Order::where('delivery_status', 'delivered')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('grand_total');

                // Total Sales Amount (all orders - for reference)
                $totalSalesAmount = \App\Models\Order::sum('grand_total') ?? 0;

                // Stock Value - sum of (purchase_quantity * buying_unit_cost)
                $stockValue = \App\Models\PurchaseProduct::sum(\DB::raw('purchase_quantity * buying_unit_cost'));

                // Today's Sales (completed orders only)
                $todaySales = Order::whereDate('created_at', today())
                    ->where('delivery_status', 'delivered')
                    ->where('payment_status', 'paid')
                    ->sum('grand_total');

                // Receivable Amount
                $receivableAmount = ChartOfAccount::where('type', 'asset')
                    ->where('sub_type', 'receivable')
                    ->where('status', 'active')
                    ->sum('current_balance');

                // Payable Amount
                $payableAmount = ChartOfAccount::where('type', 'liability')
                    ->where('sub_type', 'payable')
                    ->where('status', 'active')
                    ->sum('current_balance');

                // Current Stock Value (remaining quantity only)
                $currentStockvalue = DB::table('purchase_products')
                    ->where('remaining_qty', '>', 0)
                    ->sum(DB::raw('remaining_qty * buying_unit_cost'));

                // Petty Cash Value
                $pettyCashAccount = ChartOfAccount::getPettyCashAccount();
                $pettycashValue = $pettyCashAccount ? $pettyCashAccount->current_balance : 0;

                // ============ CURRENT YEAR EARNINGS ============

                // Current year sales (completed orders only)
                $currentYearSales = (float) Order::whereYear('created_at', $currentYear)
                    ->where('delivery_status', 'delivered')
                    ->where('payment_status', 'paid')
                    ->sum('grand_total');

                // Current year buying cost
                $currentYearBuyingCost = (float) DB::table('order_details')
                    ->join('orders', 'order_details.order_id', '=', 'orders.id')
                    ->whereYear('orders.created_at', $currentYear)
                    ->where('orders.delivery_status', 'delivered')
                    ->where('orders.payment_status', 'paid')
                    ->sum('order_details.buying_price');

                // Current year expenses (using AccountStatementLog debits to expense accounts)
                $currentYearExpenses = (float) AccountStatementLog::whereHas('account', function ($q) {
                    $q->where('type', 'expense')->where('sub_type', 'expense');
                })
                    ->whereYear('transaction_date', $currentYear)
                    ->where('debit', '>', 0)
                    ->sum('debit');



                // Current Year Earnings = Sales - Cost of Goods - Expenses
                $totalCurrentYearEarnings = $currentYearSales - $currentYearBuyingCost - $currentYearExpenses;

                // Debug logging (remove in production)
                \Log::info('Dashboard Data Loaded:', [
                    'total_income' => $totalIncome,
                    'total_expense' => $totalExpense,
                    'net_income' => $netIncome,
                    'profit_margin' => $profitMargin,
                    'monthly_income' => $monthlyIncome,
                    'monthly_expense' => $monthlyExpense,
                    'month_labels' => $monthLabels,
                    'bank_accounts_count' => $bankAccounts->count(),
                    'current_year_earnings' => $totalCurrentYearEarnings,
                ]);

                break;

            case 'chart-of-accounts':
                $assetAccounts = ChartOfAccount::where('type', 'asset')->where('status', 'active')->get();
                $liabilityAccounts = ChartOfAccount::where('type', 'liability')->where('status', 'active')->get();
                $incomeAccounts = ChartOfAccount::where('type', 'income')->where('status', 'active')->get();
                $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('status', 'active')->get();
                break;

            case 'account-statement':
                $selectedAccount = $request->input('account_id');
                $selectedDateRange = $request->input('date_range');

                $previousBalance = 0;
                $rowsHtml = '';   // we'll build the table rows directly here

                if ($selectedAccount) {
                    $dates = $selectedDateRange ? explode(' to ', $selectedDateRange) : [];
                    $startDate = null;
                    $endDate = null;
                    if (count($dates) == 2) {
                        try {
                            $startDate = \Carbon\Carbon::createFromFormat('d-m-Y', trim($dates[0]))->startOfDay();
                            $endDate = \Carbon\Carbon::createFromFormat('d-m-Y', trim($dates[1]))->endOfDay();
                        } catch (\Exception $e) {
                        }
                    }

                    // Previous balance = last balance BEFORE the selected period
                    $previousLog = AccountStatementLog::where('account_id', $selectedAccount)
                        ->when($startDate, fn($q) => $q->where('transaction_date', '<', $startDate))
                        ->orderBy('id', 'desc')
                        ->first();
                    $previousBalance = $previousLog ? $previousLog->balance : 0;

                    // Fetch logs within period
                    $logs = AccountStatementLog::where('account_id', $selectedAccount)
                        ->when($startDate, fn($q) => $q->where('transaction_date', '>=', $startDate))
                        ->when($endDate, fn($q) => $q->where('transaction_date', '<=', $endDate))
                        ->orderBy('transaction_date')
                        ->orderBy('id')
                        ->get();

                    // Determine account type for In/Out display logic
                    $selAccount = \App\Models\ChartOfAccount::find($selectedAccount);
                    $accountType = $selAccount ? $selAccount->type : 'asset';
                    $isIncomeAccount = $accountType === 'income';

                    // Build HTML rows with correct running balance
                    $runningBalance = $previousBalance;
                    foreach ($logs as $log) {
                        if ($accountType === 'income' || $accountType === 'liability') {
                            $runningBalance = $runningBalance + $log->credit - $log->debit;
                        } else {
                            $runningBalance = $runningBalance + $log->debit - $log->credit;
                        }

                        if ($accountType === 'income' || $accountType === 'expense' || $accountType === 'liability') {
                            $displayIn = $log->credit > 0 ? number_format($log->credit, 2) : '0.00';
                            $displayOut = $log->debit > 0 ? number_format($log->debit, 2) : '0.00';
                        } else {
                            $displayIn = $log->debit > 0 ? number_format($log->debit, 2) : '0.00';
                            $displayOut = $log->credit > 0 ? number_format($log->credit, 2) : '0.00';
                        }

                        $rowsHtml .= '<tr>';
                        $rowsHtml .= '<td>' . optional($log->transaction_date)->format('d M Y') . '</td>';
                        $rowsHtml .= '<td>' . $log->reference_no . '</td>';
                        $rowsHtml .= '<td>' . $log->description . '</td>';
                        $rowsHtml .= '<td class="text-right text-success">' . $displayIn . '</td>';
                        $rowsHtml .= '<td class="text-right text-danger">' . $displayOut . '</td>';
                        $rowsHtml .= '<td class="text-right font-weight-bold">' . number_format($runningBalance, 2) . '</td>';
                        $rowsHtml .= '</tr>';
                    }
                }

                break;

            case 'payment-methods':
                $assetAccounts = ChartOfAccount::where('type', 'asset')
                    ->where('status', 'active')
                    ->get();

                $bankAccounts = ChartOfAccount::whereIn('sub_type', ['bank', 'mobile_bank'])
                    ->where('status', 'active')
                    ->get();

                $mobileBankAccounts = ChartOfAccount::where('sub_type', 'mobile_bank')
                    ->where('status', 'active')
                    ->get();
                break;

            case 'supplier-payments':
                $supplierPayments = SupplierPayment::with(['supplier', 'paymentAccount',])
                    ->latest()
                    ->paginate(20);
                $suppliers = Supplier::all();
                break;

            case 'customer-payments':
                $customers = User::whereHas('orders', function ($q) {
                    $q->where('payment_status', 'paid');
                })->get();

                // Sorting
                $sort = $request->get('sort', 'settlement_date');
                $direction = $request->get('direction', 'desc');
                $allowedSorts = [
                    'settlement_no',
                    'courier_name',
                    'total_amount',
                    'settlement_fee',
                    'courier_fee',
                    'settlement_date',
                    'status',
                    'account_id',
                ];
                if (!in_array($sort, $allowedSorts)) {
                    $sort = 'settlement_date';
                }
                $direction = $direction === 'asc' ? 'asc' : 'desc';

                // Base query using CourierSettlement model
                $query = CourierSettlement::query();

                // Only load settlement details if the table exists
                if (\Schema::hasTable('courier_settlement_details')) {
                    $query->with(['order', 'user', 'account', 'settlementDetails']);
                } else {
                    $query->with(['order', 'user', 'account']);
                }

                // Rest of your filters...
                // 1. Settlement No filter
                if ($request->filled('order_no')) {
                    $query->where(function ($q) use ($request) {
                        $q->where('settlement_no', 'like', '%' . $request->order_no . '%')
                            ->orWhereHas('order', function ($subQ) use ($request) {
                                $subQ->where('code', 'like', '%' . $request->order_no . '%');
                            });
                    });
                }

                // 2. Customer search
                if ($request->filled('customer_search')) {
                    $search = $request->customer_search;
                    $query->where(function ($q) use ($search) {
                        $q->whereHas('user', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        })->orWhereHas('order', function ($subQ) use ($search) {
                            $subQ->whereRaw(
                                "JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.name')) LIKE ?",
                                ['%' . $search . '%']
                            )->orWhereRaw(
                                "JSON_UNQUOTE(JSON_EXTRACT(shipping_address, '$.phone')) LIKE ?",
                                ['%' . $search . '%']
                            );
                        });
                    });
                }

                // 3. Courier filter
                if ($request->filled('courier')) {
                    $query->where('courier_name', 'like', '%' . $request->courier . '%');
                }

                // 4. Date range
                if ($request->filled('date_range')) {
                    $dates = explode(' to ', $request->date_range);
                    if (count($dates) === 2) {
                        try {
                            $start = \Carbon\Carbon::createFromFormat('d-m-Y', trim($dates[0]))->startOfDay();
                            $end = \Carbon\Carbon::createFromFormat('d-m-Y', trim($dates[1]))->endOfDay();
                            $query->whereBetween('settlement_date', [$start, $end]);
                        } catch (\Exception $e) {
                            // ignore
                        }
                    }
                }

                $courierSettlements = $query->with(['order.orderDetails', 'user', 'account'])
                    ->orderBy($sort, $direction)
                    ->paginate(20)
                    ->appends($request->except('page'));

                break;

            case 'vouchers':
                $vouchers = Voucher::with(['creator', 'details.account'])
                    ->latest()
                    ->paginate(20);
                $costCenters = CostCenter::where('status', 'active')->get();
                break;

            case 'withdraw-deposit':
                $transactions = BalanceTransfer::with(['fromAccount', 'toAccount', 'creator'])
                    ->latest()
                    ->paginate(50);
                break;

            case 'expenses':
                $expensePayments = ExpensePayment::with(['paymentAccount'])
                    ->latest()
                    ->paginate(20);

                break;

            case 'incomes':
                $incomes = Income::with(['incomeAccount', 'receivedAccount', 'customer'])
                    ->latest()
                    ->paginate(20);
                break;
        }

        return view('backend.accounts.index', compact(
            'activeTab',
            'methods',
            'assetAccounts',
            'liabilityAccounts',
            'incomeAccounts',
            'expenseAccounts',
            'totalAssets',
            'totalLiabilities',
            'cashBankBalance',
            'currentAssets',
            'currentAssetsTotal',
            'bankAccounts',  // Make sure this is included
            'bankCashTotal', // Make sure this is included
            'fixedAssets',
            'fixedAssetsTotal',
            'currentLiabilities',
            'currentLiabilitiesTotal',
            'supplierPayments',
            'suppliers',
            'paymentAccounts',
            'customerPayments',
            'customers',
            'vouchers',
            'expensePayments',
            'incomes',
            'transactions',
            'accounts',
            'costCenters',
            'bankTransactions',
            // Dashboard specific variables
            'totalIncome',
            'totalExpense',
            'netIncome',
            'profitMargin',
            'incomingRevenue',
            'operatingExpenses',
            'netSurplus',
            'revenuePercentage',
            'expensePercentage',
            'totalAccounts',
            'reconciledAccounts',
            'reconciledPercentage',
            'pendingApprovals',
            'pendingPercentage',
            'overdueInvoices',
            'overduePercentage',
            'monthlyIncome',
            'monthlyExpense',
            'monthLabels',     // Add this
            'recentTransactions',
            'assetLabels',
            'assetValues',
            'expenseLabels',
            'expenseValues',
            'totalAssetsGrowth',
            'totalLiabilitiesGrowth',
            'netIncomeGrowth',
            'totalExpensesGrowth',
            'profitMarginGrowth',
            'totalSupplierDueBalance',
            'totalSalesAmount',
            'rowsHtml',
            'previousBalance',
            'selectedAccount',
            'selectedDateRange',
            'mobileBankAccounts',
            'courierSettlements',
            'stockValue',
            'todaySales',
            'receivableAmount',
            'payableAmount',
            'currentStockvalue',
            'pettycashValue',
            'totalCurrentYearEarnings',
            'totalMonthlySale',
            'isIncomeAccount'
        ));
    }

    public function orderSettlementDetails($id)
    {
        $order = Order::with('orderDetails.product')->findOrFail($id);
        return view('backend.accounts.customer-payments.order_settlement_details', compact('order'));
    }

    public function createBalanceTransfer(Request $request)
    {
        $accounts = ChartOfAccount::where('status', 'active')->get();
        $costCenters = CostCenter::where('status', 'active')->get();

        return view('backend.accounts.withdraw_transfer.create_edit', compact('accounts', 'costCenters'));
    }

    public function editBalanceTransfer($id, Request $request)
    {
        $transaction = BalanceTransfer::findOrFail($id);
        $accounts = ChartOfAccount::where('status', 'active')->get();
        $costCenters = CostCenter::where('status', 'active')->get();

        return view('backend.accounts.withdraw_transfer.create_edit', compact('transaction', 'accounts', 'costCenters'));
    }

    public function getStatement($accountId, Request $request)
    {
        $account = ChartOfAccount::findOrFail($accountId);

        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $statements = AccountStatementLog::where('account_id', $accountId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'account' => $account,
            'statements' => $statements,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function getBalanceSheet()
    {
        $assetAccounts = ChartOfAccount::where('type', 'asset')->where('status', 'active')->get();
        $liabilityAccounts = ChartOfAccount::where('type', 'liability')->where('status', 'active')->get();
        $equityAccounts = ChartOfAccount::where('type', 'equity')->where('status', 'active')->get();

        $currentAssets = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank', 'receivable'])
            ->where('status', 'active')
            ->get();

        $bankAccounts = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['bank', 'mobile_bank'])
            ->where('status', 'active')
            ->get();

        $fixedAssets = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['inventory', 'fixed_asset'])
            ->where('status', 'active')
            ->get();

        $currentLiabilities = ChartOfAccount::where('type', 'liability')
            ->whereIn('sub_type', ['payable', 'tax'])
            ->where('status', 'active')
            ->get();

        $totalAssets = $assetAccounts->sum('current_balance');
        $totalLiabilities = $liabilityAccounts->sum('current_balance');
        $totalEquity = $equityAccounts->sum('current_balance');

        $currentAssetsTotal = $currentAssets->sum('current_balance');
        $bankCashTotal = $bankAccounts->sum('current_balance');
        $fixedAssetsTotal = $fixedAssets->sum('current_balance');
        $currentLiabilitiesTotal = $currentLiabilities->sum('current_balance');

        return view('backend.accounts.balance_sheet', compact(
            'assetAccounts',
            'liabilityAccounts',
            'equityAccounts',
            'currentAssets',
            'bankAccounts',
            'fixedAssets',
            'currentLiabilities',
            'totalAssets',
            'totalLiabilities',
            'totalEquity',
            'currentAssetsTotal',
            'bankCashTotal',
            'fixedAssetsTotal',
            'currentLiabilitiesTotal'
        ));
    }

    public function getTrialBalance(Request $request)
    {
        $asOfDate = $request->get('as_of_date', now()->format('Y-m-d'));

        $accounts = ChartOfAccount::where('status', 'active')->get();

        $trialBalance = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {
            $debit = ($account->type === 'asset' || $account->type === 'expense')
                ? $account->current_balance
                : 0;
            $credit = ($account->type === 'liability' || $account->type === 'equity' || $account->type === 'income')
                ? $account->current_balance
                : 0;

            $totalDebit += $debit;
            $totalCredit += $credit;

            $trialBalance[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        return view('backend.accounts.trial_balance', compact('trialBalance', 'totalDebit', 'totalCredit', 'asOfDate'));
    }

    public function getProfitLoss(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $incomeAccounts = ChartOfAccount::where('type', 'income')->where('status', 'active')->get();
        $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('status', 'active')->get();

        $totalIncome = $incomeAccounts->sum('current_balance');
        $totalExpense = $expenseAccounts->sum('current_balance');
        $netProfit = $totalIncome - $totalExpense;

        return view('backend.accounts.profit_loss', compact(
            'incomeAccounts',
            'expenseAccounts',
            'totalIncome',
            'totalExpense',
            'netProfit',
            'startDate',
            'endDate'
        ));
    }

    public function getCashFlow(Request $request)
    {
        return view('backend.accounts.cash_flow');
    }

    public function getReceivablePayable(Request $request)
    {
        $receivableAccount = ChartOfAccount::where('sub_type', 'receivable')->first();
        $payableAccount = ChartOfAccount::where('sub_type', 'payable')->first();

        return view('backend.accounts.receivable_payable', compact('receivableAccount', 'payableAccount'));
    }

    public function exportReport(Request $request)
    {
        return response()->json(['message' => 'Export coming soon']);
    }



    private function getDashboardData()
    {
        // Get monthly income and expense data (last 6 months)
        $monthlyIncome = [];
        $monthlyExpense = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $incomeTotal = Income::whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->sum('total_amount');
            $expenseTotal = ExpensePayment::whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->sum('total_amount');

            $monthlyIncome[] = $incomeTotal;
            $monthlyExpense[] = $expenseTotal;
        }

        // Get recent transactions
        $recentTransactions = BalanceTransfer::with(['fromAccount', 'toAccount'])
            ->latest()
            ->take(5)
            ->get();

        // Calculate net income
        $totalIncome = Income::sum('total_amount');
        $totalExpense = ExpensePayment::sum('total_amount');
        $netIncome = $totalIncome - $totalExpense;
        $profitMargin = $totalIncome > 0 ? ($netIncome / $totalIncome) * 100 : 0;

        // Calculate revenue and expense percentages
        $incomingRevenue = $totalIncome;
        $operatingExpenses = $totalExpense;
        $netSurplus = $netIncome;

        $revenuePercentage = $totalIncome > 0 ? min(100, ($totalIncome / 1000000) * 100) : 0;
        $expensePercentage = $totalExpense > 0 ? min(100, ($totalExpense / 1000000) * 100) : 0;

        // Account health calculations
        $totalAccounts = ChartOfAccount::where('status', 'active')->count();
        $reconciledAccounts = ChartOfAccount::where('status', 'active')
            ->where('current_balance', '>', 0)
            ->count();
        $reconciledPercentage = $totalAccounts > 0 ? ($reconciledAccounts / $totalAccounts) * 100 : 0;

        $pendingApprovals = BalanceTransfer::where('status', 'pending')->count();
        $pendingPercentage = $totalAccounts > 0 ? ($pendingApprovals / $totalAccounts) * 100 : 0;

        // Asset and expense breakdown
        $assetData = ChartOfAccount::where('type', 'asset')
            ->where('status', 'active')
            ->select('sub_type', \DB::raw('SUM(current_balance) as total'))
            ->groupBy('sub_type')
            ->limit(4)
            ->get();

        $assetLabels = $assetData->pluck('sub_type')->map(function ($item) {
            return ucfirst(str_replace('_', ' ', $item));
        })->toArray();
        $assetValues = $assetData->pluck('total')->toArray();

        $expenseData = ExpensePaymentDetail::with('expenseAccount')
            ->select('expense_account_id', \DB::raw('SUM(amount) as total'))
            ->groupBy('expense_account_id')
            ->limit(3)
            ->get();

        $expenseLabels = $expenseData->map(function ($item) {
            return $item->expenseAccount->name ?? 'Other';
        })->toArray();
        $expenseValues = $expenseData->pluck('total')->toArray();

        return compact(
            'monthlyIncome',
            'monthlyExpense',
            'recentTransactions',
            'totalIncome',
            'totalExpense',
            'netIncome',
            'profitMargin',
            'incomingRevenue',
            'operatingExpenses',
            'netSurplus',
            'revenuePercentage',
            'expensePercentage',
            'totalAccounts',
            'reconciledAccounts',
            'reconciledPercentage',
            'pendingApprovals',
            'pendingPercentage',
            'assetLabels',
            'assetValues',
            'expenseLabels',
            'expenseValues'
        );
    }
}
