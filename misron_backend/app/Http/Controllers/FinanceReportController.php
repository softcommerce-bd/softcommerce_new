<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\AccountStatementLog;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PDF;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class FinanceReportController extends Controller
{

    public function profitLoss(Request $request)
    {
        $dateRange = $request->get('date_range', 'this_month');
        $dates = $this->getDateRange($dateRange, $request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // Handle Export
        if ($request->get('export') === 'excel') {
            // Calculate data first
            $data = $this->calculateProfitLossData($startDate, $endDate);
            return $this->exportProfitLossExcel(
                $data['incomeData'],
                $data['cogsData'],
                $data['expenseData'],
                $data['totalIncome'],
                $data['totalCOGS'],
                $data['grossProfit'],
                $data['totalExpenses'],
                $data['netProfit'],
                $startDate,
                $endDate
            );
        }

        if ($request->get('export') === 'pdf') {
            $data = $this->calculateProfitLossData($startDate, $endDate);
            return $this->exportProfitLossPDF(
                $data['incomeData'],
                $data['cogsData'],
                $data['expenseData'],
                $data['totalIncome'],
                $data['totalCOGS'],
                $data['grossProfit'],
                $data['totalExpenses'],
                $data['netProfit'],
                $startDate,
                $endDate
            );
        }

        // Calculate data for view
        $data = $this->calculateProfitLossData($startDate, $endDate);

        return view('backend.accounts.finance_reports.profit_loss', array_merge(
            compact('dateRange', 'startDate', 'endDate'),
            $data
        ));
    }

    /**
     * Calculate Profit & Loss data for given date range
     */
    private function calculateProfitLossData($startDate, $endDate)
    {
        // Get Income accounts
        $incomeAccounts = ChartOfAccount::where('type', 'income')
            ->where('status', 'active')
            ->get();

        $totalIncome = 0;
        $incomeData = [];
        foreach ($incomeAccounts as $account) {
            // For P&L, get transactions WITHIN the period only
            $balance = $this->getPeriodTransactions($account->id, $startDate, $endDate, 'credit');
            if ($balance != 0) {
                $incomeData[] = [
                    'name' => $account->name,
                    'code' => $account->code,
                    'balance' => $balance
                ];
                $totalIncome += $balance;
            }
        }

        // Get COGS accounts
        $cogsAccounts = ChartOfAccount::where('sub_type', 'cost_of_sales')
            ->where('status', 'active')
            ->get();

        $totalCOGS = 0;
        $cogsData = [];
        foreach ($cogsAccounts as $account) {
            $balance = $this->getPeriodTransactions($account->id, $startDate, $endDate, 'debit');
            if ($balance != 0) {
                $cogsData[] = [
                    'name' => $account->name,
                    'code' => $account->code,
                    'balance' => $balance
                ];
                $totalCOGS += $balance;
            }
        }

        $grossProfit = $totalIncome - $totalCOGS;

        // Get Expense accounts
        $expenseAccounts = ChartOfAccount::where('type', 'expense')
            ->where('sub_type', '!=', 'cost_of_sales')
            ->where('status', 'active')
            ->get();

        $totalExpenses = 0;
        $expenseData = [];
        foreach ($expenseAccounts as $account) {
            $balance = $this->getPeriodTransactions($account->id, $startDate, $endDate, 'debit');
            if ($balance != 0) {
                $expenseData[] = [
                    'name' => $account->name,
                    'code' => $account->code,
                    'balance' => $balance
                ];
                $totalExpenses += $balance;
            }
        }

        $netProfit = $grossProfit - $totalExpenses;

        return compact(
            'incomeData',
            'totalIncome',
            'cogsData',
            'totalCOGS',
            'grossProfit',
            'expenseData',
            'totalExpenses',
            'netProfit'
        );
    }

    /**
     * Get net transactions for an account within a date range
     * For P&L: Only transactions WITHIN the period
     */
    private function getPeriodTransactions($accountId, $startDate, $endDate, $type = 'debit')
    {
        $debits = AccountStatementLog::where('account_id', $accountId)
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('debit');

        $credits = AccountStatementLog::where('account_id', $accountId)
            ->whereBetween('transaction_date', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('credit');

        if ($type === 'debit') {
            return $debits - $credits;
        } else {
            return $credits - $debits;
        }
    }

    /**
     * Get account balance UP TO a specific date (for Balance Sheet)
     */
    private function getAccountBalanceUpToDate($accountId, $endDate, $type = 'debit')
    {
        $debits = AccountStatementLog::where('account_id', $accountId)
            ->where('transaction_date', '<=', $endDate->copy()->endOfDay())
            ->sum('debit');

        $credits = AccountStatementLog::where('account_id', $accountId)
            ->where('transaction_date', '<=', $endDate->copy()->endOfDay())
            ->sum('credit');

        if ($type === 'debit') {
            return $debits - $credits;
        } else {
            return $credits - $debits;
        }
    }

    /**
     * Export Profit & Loss as Premium Excel (XLSX)
     */
    private function exportProfitLossExcel($incomeData, $cogsData, $expenseData, $totalIncome, $totalCOGS, $grossProfit, $totalExpenses, $netProfit, $startDate, $endDate)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(5);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(5);

        $currentRow = 1;

        // Company Header
        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($currentRow)->setRowHeight(35);
        $currentRow++;

        // Report Title
        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "PROFIT LOSS STATEMENT");
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($currentRow)->setRowHeight(28);
        $currentRow++;

        // Period
        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "Period: " . $startDate->format('d M Y') . " to " . $endDate->format('d M Y'));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['size' => 11, 'color' => ['rgb' => '666666']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $currentRow += 2;

        // Income Section
        $this->addSectionHeader($sheet, $currentRow, 'INCOME', '1F4E79', 'FFFFFF');
        $currentRow++;
        $this->addColumnHeaders($sheet, $currentRow);
        $currentRow++;

        foreach ($incomeData as $income) {
            $this->addDataRow($sheet, $currentRow, $income['name'], $income['balance']);
            $currentRow++;
        }
        if (empty($incomeData)) {
            $this->addEmptyRow($sheet, $currentRow);
            $currentRow++;
        }
        $this->addTotalRow($sheet, $currentRow, 'Total Income', $totalIncome, 'E8F0FE');
        $currentRow += 2;

        // COGS Section
        $this->addSectionHeader($sheet, $currentRow, 'COST OF SALES', 'C55A11', 'FFFFFF');
        $currentRow++;
        $this->addColumnHeaders($sheet, $currentRow);
        $currentRow++;

        foreach ($cogsData as $cogs) {
            $this->addDataRow($sheet, $currentRow, $cogs['name'], $cogs['balance']);
            $currentRow++;
        }
        if (empty($cogsData)) {
            $this->addEmptyRow($sheet, $currentRow);
            $currentRow++;
        }
        $this->addTotalRow($sheet, $currentRow, 'Total Cost of Sales', $totalCOGS, 'FCE4D6');
        $currentRow += 2;

        // Gross Profit
        $this->addHighlightRow($sheet, $currentRow, 'GROSS PROFIT', $grossProfit, 'D9EAD3', '274E13');
        $currentRow += 2;

        // Expenses Section
        $this->addSectionHeader($sheet, $currentRow, 'EXPENSES', 'C00000', 'FFFFFF');
        $currentRow++;
        $this->addColumnHeaders($sheet, $currentRow);
        $currentRow++;

        foreach ($expenseData as $expense) {
            $this->addDataRow($sheet, $currentRow, $expense['name'], $expense['balance']);
            $currentRow++;
        }
        if (empty($expenseData)) {
            $this->addEmptyRow($sheet, $currentRow);
            $currentRow++;
        }
        $this->addTotalRow($sheet, $currentRow, 'Total Expenses', $totalExpenses, 'F4CCCC');
        $currentRow += 2;

        // Net Profit/Loss
        $netLabel = $netProfit >= 0 ? 'NET PROFIT' : 'NET LOSS';
        $netColor = $netProfit >= 0 ? '0D5C0D' : '8B0000';
        $netBgColor = $netProfit >= 0 ? 'D9EAD3' : 'F4CCCC';
        $this->addHighlightRow($sheet, $currentRow, $netLabel, abs($netProfit), $netBgColor, $netColor, true);
        $currentRow += 2;

        // Footer
        $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "Print Date: " . now()->format('d M Y H:i'));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '999999'], 'italic' => true],
        ]);
        $sheet->mergeCells("D{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("D{$currentRow}", "Generated by: " . (auth()->user()->name ?? 'System'));
        $sheet->getStyle("D{$currentRow}")->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '999999'], 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $filename = 'Profit_Loss_Statement_' . $startDate->format('d-m-Y') . '_to_' . $endDate->format('d-m-Y') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function exportProfitLossPDF($incomeData, $cogsData, $expenseData, $totalIncome, $totalCOGS, $grossProfit, $totalExpenses, $netProfit, $startDate, $endDate)
    {
        $data = compact(
            'incomeData',
            'cogsData',
            'expenseData',
            'totalIncome',
            'totalCOGS',
            'grossProfit',
            'totalExpenses',
            'netProfit',
            'startDate',
            'endDate'
        );

        $filename = 'Profit_Loss_' . $startDate->format('d-m-Y') . '_to_' . $endDate->format('d-m-Y') . '.pdf';

        try {
            $html = view('backend.accounts.finance_reports.pdf.profit_loss_pdf', $data)->render();

            // Mpdf configuration with Unicode/Bengali support
            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 12,
                'margin_bottom' => 12,
                'fontDir' => array_merge($fontDirs, [
                    public_path('fonts'), // Your custom fonts directory
                ]),
                'fontdata' => $fontData + [
                    'nikosh' => [
                        'R' => 'Nikosh.ttf',
                        'useOTL' => 0xFF,
                    ],
                    'solaimanlipi' => [
                        'R' => 'SolaimanLipi.ttf',
                        'useOTL' => 0xFF,
                    ],
                ],
                'default_font' => 'freeserif', // Good Unicode support
            ]);

            $mpdf->SetTitle('Profit Loss Statement');
            $mpdf->SetAuthor(auth()->user()->name ?? 'System');
            $mpdf->SetSubject('Profit Loss Report');
            $mpdf->SetKeywords('profit, loss, statement, report');

            // Add page numbering in footer
            $mpdf->SetFooter('{PAGENO} / {nbpg}');

            $mpdf->WriteHTML($html);

            return $mpdf->Output($filename, 'D'); // Download

        } catch (\Exception $e) {
            \Log::error('PDF generation error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return redirect()->back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    // ================== EXCEL STYLING HELPERS ==================

    private function addSectionHeader($sheet, $row, $title, $bgColor, $fontColor)
    {
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", strtoupper($title));
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => $fontColor]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(25);
        $sheet->getStyle("A{$row}")->getAlignment()->setIndent(1);
    }

    private function addColumnHeaders($sheet, $row)
    {
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", "Account Name");
        $sheet->setCellValue("D{$row}", "Amount (৳)");
        $sheet->mergeCells("D{$row}:E{$row}");
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '333333']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2F2']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '999999']]],
        ]);
        $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function addDataRow($sheet, $row, $name, $amount)
    {
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", $name);
        $sheet->setCellValue("D{$row}", number_format($amount, 2));
        $sheet->mergeCells("D{$row}:E{$row}");
        $bgColor = ($row % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'font' => ['size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
        ]);
        $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    private function addEmptyRow($sheet, $row)
    {
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", "No records found for this period");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font' => ['size' => 10, 'italic' => true, 'color' => ['rgb' => '999999']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(22);
    }

    private function addTotalRow($sheet, $row, $label, $amount, $bgColor)
    {
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", $label . " (৳):");
        $sheet->setCellValue("D{$row}", number_format($amount, 2));
        $sheet->mergeCells("D{$row}:E{$row}");
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '666666']],
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
            ],
        ]);
        $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($row)->setRowHeight(24);
    }

    private function addHighlightRow($sheet, $row, $label, $amount, $bgColor, $fontColor, $isBold = false)
    {
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", strtoupper($label) . " (৳):");
        $sheet->setCellValue("D{$row}", number_format($amount, 2));
        $sheet->mergeCells("D{$row}:E{$row}");
        $fontSize = $isBold ? 14 : 13;
        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => $fontSize, 'color' => ['rgb' => $fontColor]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $fontColor]]],
        ]);
        $sheet->getStyle("D{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($row)->setRowHeight(30);
    }

    // ================== HELPER METHODS ==================

    private function getDateRange($option, $request)
    {
        $now = Carbon::now();

        switch ($option) {


            case 'today':
                return ['start' => $now->copy()->startOfDay(), 'end' => $now->copy()->endOfDay()];
            case 'yesterday':
                return ['start' => $now->copy()->subDay()->startOfDay(), 'end' => $now->copy()->subDay()->endOfDay()];
            case 'this_week':
                return ['start' => $now->copy()->startOfWeek(), 'end' => $now->copy()->endOfWeek()];
            case 'this_month':
                return ['start' => $now->copy()->startOfMonth(), 'end' => $now->copy()->endOfMonth()];
            case 'last_month':
                return ['start' => $now->copy()->subMonth()->startOfMonth(), 'end' => $now->copy()->subMonth()->endOfMonth()];
            case 'this_year':
                return ['start' => $now->copy()->startOfYear(), 'end' => $now->copy()->endOfYear()];
            case 'custom':
                return [
                    'start' => $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : $now->copy()->startOfMonth(),
                    'end' => $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : $now->copy()->endOfMonth()
                ];

            case 'this_year_end':
                return [
                    'start' => $now->copy()->startOfYear(),
                    'end' => $now->copy()->endOfYear()
                ];
            case 'last_year_end':
                return [
                    'start' => $now->copy()->subYear()->startOfYear(),
                    'end' => $now->copy()->subYear()->endOfYear()
                ];
            default:
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth()
                ];
        }
    }



    /**
     * Balance Sheet
     */
    /**
     * Balance Sheet Report
     */
    public function balanceSheet(Request $request)
    {
        $dateRange = $request->get('date_range', 'this_year_end');
        $dates = $this->getDateRange($dateRange, $request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // Handle Export
        if ($request->get('export') === 'excel') {
            return $this->exportBalanceSheetExcel($startDate, $endDate);
        }

        if ($request->get('export') === 'pdf') {
            return $this->exportBalanceSheetPDF($startDate, $endDate);
        }

        // Calculate data for view
        $data = $this->calculateBalanceSheetData($startDate, $endDate);

        return view('backend.accounts.finance_reports.balance_sheet', array_merge(
            compact('dateRange', 'startDate', 'endDate'),
            $data
        ));
    }

    /**
     * Calculate Balance Sheet data
     */
    private function calculateBalanceSheetData($startDate, $endDate)
    {
        // Assets (using current_balance from chart_of_accounts directly)
        $assetAccounts = ChartOfAccount::where('type', 'asset')
            ->where('status', 'active')
            ->get()
            ->groupBy('sub_type');

        $totalAssets = 0;
        $assetGroups = [];

        // Define asset group order and labels
        $assetGroupOrder = ['cash', 'bank', 'mobile_bank', 'receivable', 'inventory', 'fixed_asset'];

        foreach ($assetGroupOrder as $subType) {
            if (isset($assetAccounts[$subType])) {
                $accounts = $assetAccounts[$subType];
                $groupTotal = 0;
                $items = [];
                foreach ($accounts as $account) {
                    $balance = (float) $account->current_balance;
                    if ($balance != 0) {
                        $items[] = ['name' => $account->name, 'code' => $account->code, 'balance' => $balance];
                        $groupTotal += $balance;
                    }
                }
                if (count($items) > 0) {
                    $labels = [
                        'cash' => 'Cash',
                        'bank' => 'Bank Accounts',
                        'mobile_bank' => 'Mobile Banking',
                        'receivable' => 'Accounts Receivable',
                        'inventory' => 'Inventory',
                        'fixed_asset' => 'Fixed Assets',
                    ];
                    $assetGroups[] = [
                        'label' => $labels[$subType] ?? ucfirst(str_replace('_', ' ', $subType)),
                        'items' => $items,
                        'total' => $groupTotal
                    ];
                    $totalAssets += $groupTotal;
                }
            }
        }

        // Add remaining asset types
        foreach ($assetAccounts as $subType => $accounts) {
            if (!in_array($subType, $assetGroupOrder) && !empty($subType)) {
                $groupTotal = 0;
                $items = [];
                foreach ($accounts as $account) {
                    $balance = (float) $account->current_balance;
                    if ($balance != 0) {
                        $items[] = ['name' => $account->name, 'code' => $account->code, 'balance' => $balance];
                        $groupTotal += $balance;
                    }
                }
                if (count($items) > 0) {
                    $assetGroups[] = [
                        'label' => ucfirst(str_replace('_', ' ', $subType)),
                        'items' => $items,
                        'total' => $groupTotal
                    ];
                    $totalAssets += $groupTotal;
                }
            }
        }

        // Liabilities (payable + tax only — NOT equity)
        $allLiabilityAccounts = ChartOfAccount::where('type', 'liability')
            ->where('status', 'active')
            ->get()
            ->groupBy('sub_type');

        $totalLiabilities = 0;
        $liabilityGroups = [];
        $totalEquityFromAccounts = 0;
        $equityItems = [];

        $liabilityGroupOrder = ['payable', 'tax'];

        foreach ($liabilityGroupOrder as $subType) {
            if (isset($allLiabilityAccounts[$subType])) {
                $accounts = $allLiabilityAccounts[$subType];
                $groupTotal = 0;
                $items = [];
                foreach ($accounts as $account) {
                    $balance = (float) $account->current_balance;
                    if ($balance != 0) {
                        $items[] = ['name' => $account->name, 'code' => $account->code, 'balance' => $balance];
                        $groupTotal += $balance;
                    }
                }
                if (count($items) > 0) {
                    $labels = [
                        'payable' => 'Accounts Payable',
                        'tax' => 'Tax Payable',
                    ];
                    $liabilityGroups[] = [
                        'label' => $labels[$subType] ?? ucfirst(str_replace('_', ' ', $subType)),
                        'items' => $items,
                        'total' => $groupTotal
                    ];
                    $totalLiabilities += $groupTotal;
                }
            }
        }

        // Remaining liability types (not payable/tax/equity)
        foreach ($allLiabilityAccounts as $subType => $accounts) {
            if (!in_array($subType, ['payable', 'tax', 'equity']) && !empty($subType)) {
                $groupTotal = 0;
                $items = [];
                foreach ($accounts as $account) {
                    $balance = (float) $account->current_balance;
                    if ($balance != 0) {
                        $items[] = ['name' => $account->name, 'code' => $account->code, 'balance' => $balance];
                        $groupTotal += $balance;
                    }
                }
                if (count($items) > 0) {
                    $liabilityGroups[] = [
                        'label' => ucfirst(str_replace('_', ' ', $subType)),
                        'items' => $items,
                        'total' => $groupTotal
                    ];
                    $totalLiabilities += $groupTotal;
                }
            }
        }

        // Equity accounts (sub_type = equity)
        if (isset($allLiabilityAccounts['equity'])) {
            foreach ($allLiabilityAccounts['equity'] as $account) {
                $balance = (float) $account->current_balance;
                if ($balance != 0) {
                    $equityItems[] = ['name' => $account->name, 'code' => $account->code, 'balance' => $balance];
                    $totalEquityFromAccounts += $balance;
                }
            }
        }

        // Calculate Current Year Earnings from income/expense transactions within the period
        $currentYearEarnings = $this->calculateCurrentYearEarnings($endDate);

        // Retained Earnings (plug figure to balance: Total Assets - Total Liabilities - Equity Accounts - Current Year Earnings)
        $retainedEarnings = $totalAssets - $totalLiabilities - $totalEquityFromAccounts - $currentYearEarnings;

        // Build the Equity group — Owners Equity + Current Year Earnings + Retained Earnings
        $totalEquity = $totalEquityFromAccounts;
        if ($currentYearEarnings != 0) {
            $equityItems[] = [
                'name' => 'Current Year Earnings',
                'code' => '',
                'balance' => $currentYearEarnings
            ];
            $totalEquity += $currentYearEarnings;
        }
        if ($retainedEarnings != 0) {
            $equityItems[] = [
                'name' => 'Retained Earnings',
                'code' => '',
                'balance' => $retainedEarnings
            ];
            $totalEquity += $retainedEarnings;
        }

        if (count($equityItems) > 0) {
            $liabilityGroups[] = [
                'label' => 'Equity',
                'items' => $equityItems,
                'total' => $totalEquity
            ];
        }

        return compact('assetGroups', 'totalAssets', 'liabilityGroups', 'totalLiabilities', 'totalEquity');
    }

    /**
     * Calculate Current Year Earnings using account balances
     * Uses income and expense account current_balance for consistency
     * with the Balance Sheet's asset/liability/equity calculations
     */
    private function calculateCurrentYearEarnings($endDate)
    {
        $totalIncome = (float) ChartOfAccount::where('type', 'income')
            ->sum('current_balance');

        $totalExpenses = (float) ChartOfAccount::where('type', 'expense')
            ->sum('current_balance');

        return $totalIncome - $totalExpenses;
    }

    /**
     * Export Balance Sheet as Excel
     */
    private function exportBalanceSheetExcel($startDate, $endDate)
    {
        $data = $this->calculateBalanceSheetData($startDate, $endDate);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(45);
        $sheet->getColumnDimension('C')->setWidth(5);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(5);

        $currentRow = 1;

        // Header
        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "BALANCE SHEET");
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "As at: " . $endDate->format('d M Y'));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow += 2;

        // Assets Section
        $this->addSectionHeader($sheet, $currentRow, 'ASSETS', '1F4E79', 'FFFFFF');
        $currentRow++;

        foreach ($data['assetGroups'] as $group) {
            // Group header
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $group['label']);
            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '555555']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
            ]);
            $currentRow++;

            foreach ($group['items'] as $item) {
                $this->addDataRow($sheet, $currentRow, $item['name'], $item['balance']);
                $currentRow++;
            }

            // Group total
            $this->addTotalRow($sheet, $currentRow, 'Total ' . $group['label'], $group['total'], 'E8F0FE');
            $currentRow++;
        }

        // Total Assets
        $this->addHighlightRow($sheet, $currentRow, 'TOTAL ASSETS', $data['totalAssets'], 'D9EAD3', '274E13');
        $currentRow += 2;

        // Liabilities Section
        $this->addSectionHeader($sheet, $currentRow, 'LIABILITIES & EQUITY', 'C00000', 'FFFFFF');
        $currentRow++;

        foreach ($data['liabilityGroups'] as $group) {
            $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", $group['label']);
            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '555555']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
            ]);
            $currentRow++;

            foreach ($group['items'] as $item) {
                $this->addDataRow($sheet, $currentRow, $item['name'], $item['balance']);
                $currentRow++;
            }

            $this->addTotalRow($sheet, $currentRow, 'Total ' . $group['label'], $group['total'], 'FCE4D6');
            $currentRow++;
        }

        // Total Liabilities & Equity
        $this->addHighlightRow($sheet, $currentRow, 'TOTAL LIABILITIES & EQUITY', $data['totalAssets'], 'D9EAD3', '274E13');
        $currentRow += 2;

        // Footer
        $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "Print Date: " . now()->format('d M Y H:i'));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '999999'], 'italic' => true],
        ]);
        $sheet->mergeCells("D{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("D{$currentRow}", "Generated by: " . (auth()->user()->name ?? 'System'));
        $sheet->getStyle("D{$currentRow}")->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '999999'], 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $filename = 'Balance_Sheet_' . $endDate->format('d-m-Y') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Export Balance Sheet as PDF
     */
    private function exportBalanceSheetPDF($startDate, $endDate)
    {
        $data = $this->calculateBalanceSheetData($startDate, $endDate);
        $data['startDate'] = $startDate;
        $data['endDate'] = $endDate;

        $filename = 'Balance_Sheet_' . $endDate->format('d-m-Y') . '.pdf';

        try {
            $html = view('backend.accounts.finance_reports.pdf.balance_sheet_pdf', $data)->render();

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 12,
                'margin_bottom' => 12,
                'default_font' => 'freeserif',
            ]);

            $mpdf->SetTitle("Balance Sheet - " . $endDate->format('d M Y'));
            $mpdf->WriteHTML($html);
            return $mpdf->Output($filename, 'D');
        } catch (\Exception $e) {
            \Log::error('PDF generation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Trial Balance
     */
 /**
 * Trial Balance Report
 */
public function trialBalance(Request $request)
{
    $dateRange = $request->get('date_range', 'this_year_end');
    $dates = $this->getDateRange($dateRange, $request);
    $startDate = $dates['start'];
    $endDate = $dates['end'];

    // Handle Export
    if ($request->get('export') === 'excel') {
        return $this->exportTrialBalanceExcel($startDate, $endDate);
    }

    if ($request->get('export') === 'pdf') {
        return $this->exportTrialBalancePDF($startDate, $endDate);
    }

    // Calculate data
    $data = $this->calculateTrialBalanceData($startDate, $endDate);

    return view('backend.accounts.finance_reports.trial_balance', array_merge(
        compact('dateRange', 'startDate', 'endDate'),
        $data
    ));
}

/**
 * Calculate Trial Balance data
 */
private function calculateTrialBalanceData($startDate, $endDate)
{
    $accounts = ChartOfAccount::where('status', 'active')
        ->whereNotNull('sub_type')
        ->where('sub_type', '!=', '')
        ->orderBy('code')
        ->get();

    $accountIds = $accounts->pluck('id')->toArray();

    // Fetch all statement logs up to endDate for these accounts
    $allLogs = AccountStatementLog::whereIn('account_id', $accountIds)
        ->where('transaction_date', '<=', $endDate->format('Y-m-d'))
        ->orderBy('account_id')
        ->orderBy('id')
        ->get()
        ->groupBy('account_id');

    $trialData = [];
    $totalDebit = 0;
    $totalCredit = 0;

    foreach ($accounts as $account) {
        $balance = (float) $account->current_balance;
        $netDebit = 0;
        $netCredit = 0;

        if (in_array($account->type, ['asset', 'expense'])) {
            if ($balance > 0) {
                $netDebit = $balance;
            } elseif ($balance < 0) {
                $netCredit = abs($balance);
            }
        } else {
            if ($balance > 0) {
                $netCredit = $balance;
            } elseif ($balance < 0) {
                $netDebit = abs($balance);
            }
        }

        $entries = [];
        $accountLogs = $allLogs->get($account->id, collect());
        foreach ($accountLogs as $log) {
            $entries[] = [
                'date' => $log->transaction_date instanceof \Carbon\Carbon ? $log->transaction_date->format('Y-m-d') : $log->transaction_date,
                'reference_no' => $log->reference_no,
                'transaction_type' => $log->transaction_type,
                'debit' => (float) $log->debit,
                'credit' => (float) $log->credit,
                'description' => $log->description,
            ];
        }

        if ($netDebit != 0 || $netCredit != 0 || count($entries) > 0) {
            $trialData[] = [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'sub_type' => $account->sub_type,
                'debit' => $netDebit,
                'credit' => $netCredit,
                'entries' => $entries,
            ];
            $totalDebit += $netDebit;
            $totalCredit += $netCredit;
        }
    }

    return compact('trialData', 'totalDebit', 'totalCredit');
}

/**
 * Export Trial Balance as Excel
 */
private function exportTrialBalanceExcel($startDate, $endDate)
{
    $data = $this->calculateTrialBalanceData($startDate, $endDate);
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->getColumnDimension('A')->setWidth(5);
    $sheet->getColumnDimension('B')->setWidth(35);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(22);
    $sheet->getColumnDimension('F')->setWidth(22);
    
    $currentRow = 1;
    
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
    $sheet->getStyle("A{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $currentRow++;
    
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "TRIAL BALANCE");
    $sheet->getStyle("A{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2E75B6']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $currentRow++;
    
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "As at: " . $endDate->format('d M Y'));
    $sheet->getStyle("A{$currentRow}")->applyFromArray([
        'font' => ['size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $currentRow += 2;
    
    $headers = ['SL', 'Account / Entry', 'Reference', 'Type', 'Debit (৳)', 'Credit (৳)'];
    
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $currentRow, $header);
        $col++;
    }
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    $currentRow++;
    
    $sl = 1;
    foreach ($data['trialData'] as $item) {
        // Account header row
        $sheet->setCellValue("A{$currentRow}", $sl++);
        $sheet->setCellValue("B{$currentRow}", $item['name']);
        $sheet->setCellValue("C{$currentRow}", $item['code']);
        $typeLabel = match($item['type']) {
            'asset' => 'Asset', 'liability' => 'Liability', 'income' => 'Income', 'expense' => 'Expense',
            default => $item['type'],
        };
        if (!empty($item['sub_type'])) $typeLabel .= ' (' . str_replace('_', ' ', $item['sub_type']) . ')';
        $sheet->setCellValue("D{$currentRow}", $typeLabel);
        $sheet->setCellValue("E{$currentRow}", $item['debit'] > 0 ? number_format($item['debit'], 2) : '-');
        $sheet->setCellValue("F{$currentRow}", $item['credit'] > 0 ? number_format($item['credit'], 2) : '-');
        
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8F0FE']],
            'font' => ['bold' => true, 'size' => 10],
        ]);
        $sheet->getStyle("E{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $currentRow++;
        
        // Entry rows
        if (count($item['entries']) > 0) {
            foreach ($item['entries'] as $entry) {
                $sheet->setCellValue("A{$currentRow}", '');
                $sheet->setCellValue("B{$currentRow}", '    ' . ($entry['description'] ?? ''));
                $sheet->setCellValue("C{$currentRow}", $entry['reference_no']);
                $sheet->setCellValue("D{$currentRow}", str_replace('_', ' ', $entry['transaction_type']));
                $sheet->setCellValue("E{$currentRow}", $entry['debit'] > 0 ? number_format($entry['debit'], 2) : '-');
                $sheet->setCellValue("F{$currentRow}", $entry['credit'] > 0 ? number_format($entry['credit'], 2) : '-');
                
                $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '444444']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                ]);
                $sheet->getStyle("E{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $currentRow++;
            }
        } else {
            $sheet->setCellValue("B{$currentRow}", '    (No individual entries)');
            $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
                'font' => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '999999']],
            ]);
            $currentRow++;
        }
    }
    
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D4EDDA']],
        'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '28A745']],
                      'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '28A745']]],
    ]);
    $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "TOTAL");
    $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->setCellValue("E{$currentRow}", number_format($data['totalDebit'], 2));
    $sheet->setCellValue("F{$currentRow}", number_format($data['totalCredit'], 2));
    $sheet->getStyle("E{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $currentRow += 2;
    
    $sheet->mergeCells("A{$currentRow}:C{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "Print Date: " . now()->format('d M Y H:i'));
    $sheet->getStyle("A{$currentRow}")->applyFromArray([
        'font' => ['size' => 8, 'color' => ['rgb' => '999999'], 'italic' => true],
    ]);
    $sheet->mergeCells("D{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("D{$currentRow}", "Generated by: " . (auth()->user()->name ?? 'System'));
    $sheet->getStyle("D{$currentRow}")->applyFromArray([
        'font' => ['size' => 8, 'color' => ['rgb' => '999999'], 'italic' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
    
    $filename = 'Trial_Balance_' . $endDate->format('d-m-Y') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export Trial Balance as PDF
 */
private function exportTrialBalancePDF($startDate, $endDate)
{
    $data = $this->calculateTrialBalanceData($startDate, $endDate);
    $data['startDate'] = $startDate;
    $data['endDate'] = $endDate;
    
    $filename = 'Trial_Balance_' . $endDate->format('d-m-Y') . '.pdf';

    try {
        $html = view('backend.accounts.finance_reports.pdf.trial_balance_pdf', $data)->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'default_font' => 'freeserif',
        ]);

        $mpdf->SetTitle("Trial Balance - " . $endDate->format('d M Y'));
        $mpdf->WriteHTML($html);
        return $mpdf->Output($filename, 'D');
        
    } catch (\Exception $e) {
        \Log::error('PDF generation error: ' . $e->getMessage());
        return redirect()->back()->with('error', 'PDF generation failed: ' . $e->getMessage());
    }
}

    /**
     * Cash Book Report All methods
     */
    /**
     * Cash Book Report
     */
    public function cashBook(Request $request)
    {
        $dateRange = $request->get('date_range', 'this_month');
        $dates = $this->getDateRange($dateRange, $request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        $accountId = $request->get('account_id');

        // Get cash/bank accounts for dropdown
        $cashBankAccounts = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->get();

        // Handle Export
        if ($request->get('export') === 'excel' && $accountId) {
            return $this->exportCashBookExcel($accountId, $startDate, $endDate);
        }

        if ($request->get('export') === 'pdf' && $accountId) {
            return $this->exportCashBookPDF($accountId, $startDate, $endDate);
        }

        $transactions = collect();
        $openingBalance = 0;
        $closingBalance = 0;
        $selectedAccount = null;
        $totalDebit = 0;
        $totalCredit = 0;

        if ($accountId) {
            $selectedAccount = ChartOfAccount::find($accountId);

            // Get opening balance (balance before start date)
            $openingBalance = $this->getAccountBalanceBeforeDate($accountId, $startDate);

            // Get transactions for the period
            $transactions = AccountStatementLog::where('account_id', $accountId)
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            // Calculate running balance
            $runningBalance = $openingBalance;
            foreach ($transactions as $trans) {
                $runningBalance = $runningBalance + $trans->debit - $trans->credit;
                $trans->running_balance = $runningBalance;
            }

            $closingBalance = $runningBalance;
            $totalDebit = $transactions->sum('debit');
            $totalCredit = $transactions->sum('credit');
        }

        return view('backend.accounts.finance_reports.cash_book', compact(
            'dateRange',
            'startDate',
            'endDate',
            'cashBankAccounts',
            'accountId',
            'selectedAccount',
            'transactions',
            'openingBalance',
            'closingBalance',
            'totalDebit',
            'totalCredit'
        ));
    }

    /**
     * Export Cash Book as Excel
     */
    private function exportCashBookExcel($accountId, $startDate, $endDate)
    {
        $account = ChartOfAccount::find($accountId);
        $openingBalance = $this->getAccountBalanceBeforeDate($accountId, $startDate);

        $transactions = AccountStatementLog::where('account_id', $accountId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(40);

        $currentRow = 1;

        // Header
        $sheet->mergeCells("A{$currentRow}:G{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet->mergeCells("A{$currentRow}:G{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "CASH BOOK REPORT");
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet->mergeCells("A{$currentRow}:G{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "Account: " . $account->name . " | Period: " . $startDate->format('d M Y') . " to " . $endDate->format('d M Y'));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow += 2;

        // Opening Balance
        $sheet->setCellValue("A{$currentRow}", "Opening Balance:");
        $sheet->setCellValue("C{$currentRow}", number_format($openingBalance, 2));
        $sheet->getStyle("A{$currentRow}:C{$currentRow}")->applyFromArray(['font' => ['bold' => true]]);
        $currentRow += 2;

        // Column Headers
        $headers = ['SL', 'Date', 'Reference No', 'Debit (৳)', 'Credit (৳)', 'Balance (৳)', 'Description'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $currentRow, $header);
            $col++;
        }
        $sheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $currentRow++;

        // Data
        $sl = 1;
        $runningBalance = $openingBalance;
        foreach ($transactions as $trans) {
            $runningBalance = $runningBalance + $trans->debit - $trans->credit;

            $sheet->setCellValue("A{$currentRow}", $sl++);
            $sheet->setCellValue("B{$currentRow}", Carbon::parse($trans->transaction_date)->format('d-m-Y'));
            $sheet->setCellValue("C{$currentRow}", $trans->reference_no);
            $sheet->setCellValue("D{$currentRow}", $trans->debit > 0 ? number_format($trans->debit, 2) : '-');
            $sheet->setCellValue("E{$currentRow}", $trans->credit > 0 ? number_format($trans->credit, 2) : '-');
            $sheet->setCellValue("F{$currentRow}", number_format($runningBalance, 2));
            $sheet->setCellValue("G{$currentRow}", $trans->description);

            $bgColor = ($sl % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
            $sheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            ]);
            $currentRow++;
        }

        // Closing Balance
        $currentRow++;
        $sheet->setCellValue("A{$currentRow}", "Closing Balance:");
        $sheet->setCellValue("C{$currentRow}", number_format($runningBalance, 2));
        $sheet->getStyle("A{$currentRow}:C{$currentRow}")->applyFromArray(['font' => ['bold' => true]]);

        $filename = 'Cash_Book_' . $account->name . '_' . $startDate->format('d-m-Y') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Export Cash Book as PDF
     */
    private function exportCashBookPDF($accountId, $startDate, $endDate)
    {
        $account = ChartOfAccount::find($accountId);
        $openingBalance = $this->getAccountBalanceBeforeDate($accountId, $startDate);

        $transactions = AccountStatementLog::where('account_id', $accountId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        $runningBalance = $openingBalance;
        foreach ($transactions as $trans) {
            $runningBalance = $runningBalance + $trans->debit - $trans->credit;
            $trans->running_balance = $runningBalance;
        }

        $totalDebit = $transactions->sum('debit');
        $totalCredit = $transactions->sum('credit');

        $data = compact('account', 'transactions', 'openingBalance', 'runningBalance', 'totalDebit', 'totalCredit', 'startDate', 'endDate');
        $filename = 'Cash_Book_' . $account->name . '_' . $startDate->format('d-m-Y') . '.pdf';

        try {
            $html = view('backend.accounts.finance_reports.pdf.cash_book_pdf', $data)->render();

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'default_font' => 'freeserif',
            ]);

            $mpdf->SetTitle("Cash Book Report - " . $account->name);
            $mpdf->WriteHTML($html);
            return $mpdf->Output($filename, 'D');
        } catch (\Exception $e) {
            \Log::error('PDF generation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Day Closing Report
     */
    /**
     * Day Closing Report
     */
    public function dayClosing(Request $request)
    {
        $dateRange = $request->get('date_range', 'today');

        if ($dateRange === 'today') {
            $date = now()->toDateString();
        } elseif ($dateRange === 'yesterday') {
            $date = now()->subDay()->toDateString();
        } elseif ($dateRange === 'custom' && $request->start_date) {
            $date = Carbon::parse($request->start_date)->toDateString();
        } else {
            $date = $request->get('date', now()->toDateString());
        }

        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        if ($request->get('export') === 'excel') {
            return $this->exportDayClosingExcel($date, $startDate, $endDate);
        }

        if ($request->get('export') === 'pdf') {
            return $this->exportDayClosingPDF($date, $startDate, $endDate);
        }

        // Get all transactions for the day
        $transactions = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->with('account')
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        // Cash/bank account IDs
        $cashBankIds = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->pluck('id');

        // Deposit = money received (debits to cash/bank accounts today)
        $deposit = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('account_id', $cashBankIds)
            ->sum('debit');

        // Withdraw = money paid (credits from cash/bank accounts today)
        $withdraw = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('account_id', $cashBankIds)
            ->sum('credit');

        // Closing balance = current total of all cash/bank accounts
        $closingBalance = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->sum('current_balance');

        // Opening balance = closing - deposit + withdraw
        $openingBalance = $closingBalance - $deposit + $withdraw;

        $summary = compact(
            'openingBalance',
            'deposit',
            'withdraw',
            'closingBalance'
        );

        return view('backend.accounts.finance_reports.day_closing', compact(
            'dateRange',
            'date',
            'startDate',
            'endDate',
            'transactions',
            'summary'
        ));
    }

    /**
     * Export Day Closing as Excel
     */
    /**
     * Export Day Closing as Excel
     */
    private function exportDayClosingExcel($date, $startDate, $endDate)
    {
        $transactions = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->with('account')
            ->orderBy('id', 'desc')
            ->get();

        $cashBankIds = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->pluck('id');

        $deposit = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('account_id', $cashBankIds)->sum('debit');

        $withdraw = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('account_id', $cashBankIds)->sum('credit');

        $closingBalance = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->sum('current_balance');

        $openingBalance = $closingBalance - $deposit + $withdraw;

        $spreadsheet = new Spreadsheet();

        // ==================== SHEET 1: Summary ====================
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Summary');

        $sheet1->getColumnDimension('A')->setWidth(35);
        $sheet1->getColumnDimension('B')->setWidth(20);

        $currentRow = 1;

        $sheet1->mergeCells("A{$currentRow}:B{$currentRow}");
        $sheet1->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
        $sheet1->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet1->mergeCells("A{$currentRow}:B{$currentRow}");
        $sheet1->setCellValue("A{$currentRow}", "DAY CLOSING REPORT");
        $sheet1->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet1->mergeCells("A{$currentRow}:B{$currentRow}");
        $sheet1->setCellValue("A{$currentRow}", "Date: " . Carbon::parse($date)->format('d M Y') . " | Transactions: " . $transactions->count());
        $sheet1->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow += 2;

        // Summary Header
        $sheet1->setCellValue("A{$currentRow}", "Summary");
        $sheet1->setCellValue("B{$currentRow}", "Amount (৳)");
        $sheet1->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $currentRow++;

        $summaryData = [
            ['Opening', $openingBalance],
            ['Deposit', $deposit],
            ['Withdraw', $withdraw],
            ['Closing', $closingBalance],
        ];

        foreach ($summaryData as $data) {
            $sheet1->setCellValue("A{$currentRow}", $data[0]);
            $sheet1->setCellValue("B{$currentRow}", number_format($data[1], 2));
            $sheet1->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet1->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray([
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            ]);
            $currentRow++;
        }

        // ==================== SHEET 2: Transactions ====================
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Transactions');

        $sheet2->getColumnDimension('A')->setWidth(6);
        $sheet2->getColumnDimension('B')->setWidth(12);
        $sheet2->getColumnDimension('C')->setWidth(18);
        $sheet2->getColumnDimension('D')->setWidth(25);
        $sheet2->getColumnDimension('E')->setWidth(15);
        $sheet2->getColumnDimension('F')->setWidth(16);
        $sheet2->getColumnDimension('G')->setWidth(16);
        $sheet2->getColumnDimension('H')->setWidth(45);

        $currentRow = 1;

        // Header
        $sheet2->mergeCells("A{$currentRow}:H{$currentRow}");
        $sheet2->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
        $sheet2->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet2->mergeCells("A{$currentRow}:H{$currentRow}");
        $sheet2->setCellValue("A{$currentRow}", "ALL TRANSACTIONS - " . Carbon::parse($date)->format('d M Y'));
        $sheet2->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow += 2;

        // Column Headers
        $headers = ['SL', 'Time', 'Reference No', 'Account', 'Type', 'Debit (৳)', 'Credit (৳)', 'Description'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet2->setCellValue($col . $currentRow, $header);
            $col++;
        }
        $sheet2->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $currentRow++;

        // Data Rows
        $sl = 1;
        foreach ($transactions as $trans) {
            $sheet2->setCellValue("A{$currentRow}", $sl++);
            $sheet2->setCellValue("B{$currentRow}", Carbon::parse($trans->transaction_date)->format('H:i'));
            $sheet2->setCellValue("C{$currentRow}", $trans->reference_no);
            $sheet2->setCellValue("D{$currentRow}", $trans->account->name ?? 'N/A');
            $sheet2->setCellValue("E{$currentRow}", $trans->transaction_type);
            $sheet2->setCellValue("F{$currentRow}", $trans->debit > 0 ? number_format($trans->debit, 2) : '-');
            $sheet2->setCellValue("G{$currentRow}", $trans->credit > 0 ? number_format($trans->credit, 2) : '-');
            $sheet2->setCellValue("H{$currentRow}", $trans->description);

            // Styling
            $sheet2->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet2->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $bgColor = ($sl % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
            $sheet2->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
                'font' => ['size' => 9],
            ]);
            $currentRow++;
        }

        // Totals Row
        $sheet2->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F4F8']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
        $sheet2->setCellValue("A{$currentRow}", '');
        $sheet2->setCellValue("E{$currentRow}", 'TOTAL:');
        $sheet2->setCellValue("F{$currentRow}", number_format($transactions->sum('debit'), 2));
        $sheet2->setCellValue("G{$currentRow}", number_format($transactions->sum('credit'), 2));
        $sheet2->setCellValue("H{$currentRow}", '');
        $sheet2->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet2->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet2->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Set first sheet as active
        $spreadsheet->setActiveSheetIndex(0);

        // Output
        $filename = 'Day_Closing_' . Carbon::parse($date)->format('d-m-Y') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Export Day Closing as PDF
     */
    private function exportDayClosingPDF($date, $startDate, $endDate)
    {
        $transactions = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->with('account')
            ->orderBy('id', 'desc')
            ->get();

        $cashBankIds = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->pluck('id');

        $deposit = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('account_id', $cashBankIds)->sum('debit');

        $withdraw = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereIn('account_id', $cashBankIds)->sum('credit');

        $closingBalance = ChartOfAccount::where('type', 'asset')
            ->whereIn('sub_type', ['cash', 'bank', 'mobile_bank'])
            ->where('status', 'active')
            ->sum('current_balance');

        $openingBalance = $closingBalance - $deposit + $withdraw;

        $data = compact('date', 'transactions', 'openingBalance', 'deposit', 'withdraw', 'closingBalance');
        $filename = 'Day_Closing_' . Carbon::parse($date)->format('d-m-Y') . '.pdf';

        try {
            $html = view('backend.accounts.finance_reports.pdf.day_closing_pdf', $data)->render();

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'default_font' => 'freeserif',
            ]);

            $mpdf->SetTitle("Day Closing Report - " . Carbon::parse($date)->format('d M Y'));
            $mpdf->WriteHTML($html);
            return $mpdf->Output($filename, 'D');
        } catch (\Exception $e) {
            \Log::error('PDF generation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Expense Detail Report
     */
   /**
 * Expense Detail Report
 */
public function expenseDetail(Request $request)
{
    $dateRange = $request->get('date_range', 'this_month');
    $dates = $this->getDateRange($dateRange, $request);
    $startDate = $dates['start'];
    $endDate = $dates['end'];
    $accountIds = $request->get('account_ids', []);
    $isAllExpenses = $request->get('all_expenses', false);

    // Get expense accounts for dropdown
    $expenseAccounts = ChartOfAccount::where('type', 'expense')
        ->where('status', 'active')
        ->get();

    // Handle Export
    if ($request->get('export') === 'excel') {
        return $this->exportExpenseDetailExcel($accountIds, $isAllExpenses, $startDate, $endDate);
    }

    if ($request->get('export') === 'pdf') {
        return $this->exportExpenseDetailPDF($accountIds, $isAllExpenses, $startDate, $endDate);
    }

    $transactions = collect();
    $totalExpense = 0;
    $selectedAccounts = collect();

    // If specific accounts selected or all expenses
    if ($isAllExpenses || !empty($accountIds)) {
        $query = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->where('debit', '>', 0)
            ->with('account');
        
        if (!$isAllExpenses && !empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
            $selectedAccounts = ChartOfAccount::whereIn('id', $accountIds)->get();
        } else {
            $selectedAccounts = $expenseAccounts;
        }
        
        // Only expense accounts
        $query->whereHas('account', function($q) {
            $q->where('type', 'expense');
        });
        
        $transactions = $query->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
        
        $totalExpense = $transactions->sum('debit');
    }

    return view('backend.accounts.finance_reports.expense_detail', compact(
        'dateRange', 'startDate', 'endDate',
        'expenseAccounts', 'accountIds', 'isAllExpenses',
        'transactions', 'totalExpense', 'selectedAccounts'
    ));
}

/**
 * Export Expense Detail as Excel
 */
private function exportExpenseDetailExcel($accountIds, $isAllExpenses, $startDate, $endDate)
{
    $query = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
        ->where('debit', '>', 0)
        ->with('account')
        ->whereHas('account', function($q) {
            $q->where('type', 'expense');
        });
    
    if (!$isAllExpenses && !empty($accountIds)) {
        $query->whereIn('account_id', $accountIds);
    }
    
    $transactions = $query->orderBy('transaction_date')->orderBy('id')->get();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(22);
    $sheet->getColumnDimension('D')->setWidth(25);
    $sheet->getColumnDimension('E')->setWidth(18);
    $sheet->getColumnDimension('F')->setWidth(45);
    
    $currentRow = 1;
    
    // Header
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
    $sheet->getStyle("A{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $currentRow++;
    
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "EXPENSE DETAIL REPORT");
    $sheet->getStyle("A{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2E75B6']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $currentRow++;
    
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "Period: " . $startDate->format('d M Y') . " to " . $endDate->format('d M Y'));
    $currentRow += 2;
    
    // Column Headers
    $headers = ['SL', 'Date', 'Reference No', 'Account', 'Amount (৳)', 'Description'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $currentRow, $header);
        $col++;
    }
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    $currentRow++;
    
    // Data
    $sl = 1;
    foreach ($transactions as $trans) {
        $sheet->setCellValue("A{$currentRow}", $sl++);
        $sheet->setCellValue("B{$currentRow}", Carbon::parse($trans->transaction_date)->format('d-m-Y'));
        $sheet->setCellValue("C{$currentRow}", $trans->reference_no);
        $sheet->setCellValue("D{$currentRow}", $trans->account->name ?? 'N/A');
        $sheet->setCellValue("E{$currentRow}", number_format($trans->debit, 2));
        $sheet->setCellValue("F{$currentRow}", $trans->description);
        
        $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $bgColor = ($sl % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
            'font' => ['size' => 10],
        ]);
        $currentRow++;
    }
    
    // Total
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F4F8']],
        'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
    ]);
    $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "TOTAL");
    $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->setCellValue("E{$currentRow}", number_format($transactions->sum('debit'), 2));
    $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    $filename = 'Expense_Detail_' . $startDate->format('d-m-Y') . '_to_' . $endDate->format('d-m-Y') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export Expense Detail as PDF
 */
private function exportExpenseDetailPDF($accountIds, $isAllExpenses, $startDate, $endDate)
{
    $query = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
        ->where('debit', '>', 0)
        ->with('account')
        ->whereHas('account', function($q) {
            $q->where('type', 'expense');
        });
    
    if (!$isAllExpenses && !empty($accountIds)) {
        $query->whereIn('account_id', $accountIds);
    }
    
    $transactions = $query->orderBy('transaction_date')->orderBy('id')->get();
    $totalExpense = $transactions->sum('debit');
    
    $data = compact('transactions', 'totalExpense', 'startDate', 'endDate');
    $filename = 'Expense_Detail_' . $startDate->format('d-m-Y') . '_to_' . $endDate->format('d-m-Y') . '.pdf';

    try {
        $html = view('backend.accounts.finance_reports.pdf.expense_detail_pdf', $data)->render();

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'default_font' => 'freeserif',
        ]);

        $mpdf->SetTitle("Expense Detail Report");
        $mpdf->WriteHTML($html);
        return $mpdf->Output($filename, 'D');
        
    } catch (\Exception $e) {
        \Log::error('PDF generation error: ' . $e->getMessage());
        return redirect()->back()->with('error', 'PDF generation failed: ' . $e->getMessage());
    }
}

    /**
     * Expense Summary Report
     */
   /**
 * Expense Summary Report
 */
public function expenseSummary(Request $request)
{
    $dateRange = $request->get('date_range', 'this_month');
    $dates = $this->getDateRange($dateRange, $request);
    $startDate = $dates['start'];
    $endDate = $dates['end'];

    // Handle Export
    if ($request->get('export') === 'excel') {
        return $this->exportExpenseSummaryExcel($startDate, $endDate);
    }

    if ($request->get('export') === 'pdf') {
        return $this->exportExpenseSummaryPDF($startDate, $endDate);
    }

    $expenseAccounts = ChartOfAccount::where('type', 'expense')
        ->where('status', 'active')
        ->get();

    $summaryData = [];
    $grandTotal = 0;

    foreach ($expenseAccounts as $account) {
        $total = AccountStatementLog::where('account_id', $account->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('debit', '>', 0)
            ->sum('debit');

        $count = AccountStatementLog::where('account_id', $account->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('debit', '>', 0)
            ->count();

        if ($total > 0) {
            $summaryData[] = [
                'name' => $account->name,
                'code' => $account->code,
                'total' => $total,
                'count' => $count
            ];
            $grandTotal += $total;
        }
    }

    // Sort by total descending
    usort($summaryData, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    return view('backend.accounts.finance_reports.expense_summary', compact(
        'dateRange', 'startDate', 'endDate',
        'summaryData', 'grandTotal'
    ));
}

/**
 * Export Expense Summary as Excel
 */
private function exportExpenseSummaryExcel($startDate, $endDate)
{
    $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('status', 'active')->get();
    
    $summaryData = [];
    $grandTotal = 0;
    
    foreach ($expenseAccounts as $account) {
        $total = AccountStatementLog::where('account_id', $account->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('debit', '>', 0)
            ->sum('debit');
        $count = AccountStatementLog::where('account_id', $account->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('debit', '>', 0)
            ->count();
        
        if ($total > 0) {
            $summaryData[] = ['name' => $account->name, 'code' => $account->code, 'total' => $total, 'count' => $count];
            $grandTotal += $total;
        }
    }
    
    usort($summaryData, function($a, $b) { return $b['total'] <=> $a['total']; });

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(40);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(22);
    $sheet->getColumnDimension('F')->setWidth(20);
    
    $currentRow = 1;
    
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
    $sheet->getStyle("A{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $currentRow++;
    
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "EXPENSE SUMMARY REPORT");
    $currentRow++;
    
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "Period: " . $startDate->format('d M Y') . " to " . $endDate->format('d M Y'));
    $currentRow += 2;
    
    $headers = ['SL', 'Account Code', 'Expense Head', 'Entries', 'Total (৳)', '% of Total'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $currentRow, $header);
        $col++;
    }
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    $currentRow++;
    
    $sl = 1;
    foreach ($summaryData as $item) {
        $percentage = $grandTotal > 0 ? round(($item['total'] / $grandTotal) * 100, 1) : 0;
        $sheet->setCellValue("A{$currentRow}", $sl++);
        $sheet->setCellValue("B{$currentRow}", $item['code']);
        $sheet->setCellValue("C{$currentRow}", $item['name']);
        $sheet->setCellValue("D{$currentRow}", $item['count']);
        $sheet->setCellValue("E{$currentRow}", number_format($item['total'], 2));
        $sheet->setCellValue("F{$currentRow}", $percentage . '%');
        
        $sheet->getStyle("E{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $bgColor = ($sl % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $currentRow++;
    }
    
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9EAD3']],
        'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
    ]);
    $sheet->mergeCells("A{$currentRow}:D{$currentRow}");
    $sheet->setCellValue("A{$currentRow}", "GRAND TOTAL");
    $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->setCellValue("E{$currentRow}", number_format($grandTotal, 2));
    $sheet->setCellValue("F{$currentRow}", '100%');
    
    $filename = 'Expense_Summary_' . $startDate->format('d-m-Y') . '_to_' . $endDate->format('d-m-Y') . '.xlsx';
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * Export Expense Summary as PDF
 */
private function exportExpenseSummaryPDF($startDate, $endDate)
{
    // Same calculation as above
    $expenseAccounts = ChartOfAccount::where('type', 'expense')->where('status', 'active')->get();
    $summaryData = [];
    $grandTotal = 0;
    
    foreach ($expenseAccounts as $account) {
        $total = AccountStatementLog::where('account_id', $account->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('debit', '>', 0)->sum('debit');
        $count = AccountStatementLog::where('account_id', $account->id)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('debit', '>', 0)->count();
        if ($total > 0) {
            $summaryData[] = ['name' => $account->name, 'code' => $account->code, 'total' => $total, 'count' => $count];
            $grandTotal += $total;
        }
    }
    usort($summaryData, function($a, $b) { return $b['total'] <=> $a['total']; });
    
    $data = compact('summaryData', 'grandTotal', 'startDate', 'endDate');
    $filename = 'Expense_Summary_' . $startDate->format('d-m-Y') . '_to_' . $endDate->format('d-m-Y') . '.pdf';

    try {
        $html = view('backend.accounts.finance_reports.pdf.expense_summary_pdf', $data)->render();
        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'orientation' => 'P', 'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 10, 'margin_bottom' => 10, 'default_font' => 'freeserif']);
        $mpdf->SetTitle("Expense Summary Report");
        $mpdf->WriteHTML($html);
        return $mpdf->Output($filename, 'D');
    } catch (\Exception $e) {
        \Log::error('PDF error: ' . $e->getMessage());
        return redirect()->back()->with('error', 'PDF generation failed');
    }
}

    /**
     * Today's Transaction All Parts 
     */
    /**
     * Today's Transaction Report
     */
    public function todayTransactions(Request $request)
    {
        $dateRange = $request->get('date_range', 'today');

        if ($dateRange === 'today') {
            $date = now()->toDateString();
        } elseif ($dateRange === 'yesterday') {
            $date = now()->subDay()->toDateString();
        } elseif ($dateRange === 'custom' && $request->start_date) {
            $date = Carbon::parse($request->start_date)->toDateString();
        } else {
            $date = $request->get('date', now()->toDateString());
        }

        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        if ($request->get('export') === 'excel') {
            return $this->exportTodayTransactionsExcel($date, $startDate, $endDate);
        }

        if ($request->get('export') === 'pdf') {
            return $this->exportTodayTransactionsPDF($date, $startDate, $endDate);
        }

        // Get all transactions for the day (unpaginated to allow pairing), exclude internal cost allocations
        $logs = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereNotIn('transaction_type', ['order_cogs', 'order_inventory', 'courier_settlement_cogs'])
            ->with('account', 'voucher')
            ->orderBy('id', 'desc')
            ->get();

        // Determine section for a log entry
        $getSection = function ($log) {
            // Voucher-based entries (voucher_id set directly from postToAccounts like CustomerPaymentController)
            if ($log->voucher_id && $log->voucher) {
                $map = ['BP' => 'Bank Payment', 'CP' => 'Cash Payment', 'BR' => 'Bank Receive', 'CR' => 'Cash Receive', 'JV' => 'Journal Voucher', 'CV' => 'Contra'];
                $customMap = ['receipt' => 'Cash Receive', 'payment' => 'Cash Payment'];
                return $customMap[$log->voucher->type] ?? $map[$log->voucher->type] ?? 'Journal Voucher';
            }
            // Voucher entries via StatementLogTrait (voucher_id NOT set, but source_type = Voucher)
            if (str_contains($log->source_type ?? '', 'Voucher')) {
                $map = ['BP' => 'Bank Payment', 'CP' => 'Cash Payment', 'BR' => 'Bank Receive', 'CR' => 'Cash Receive', 'JV' => 'Journal Voucher', 'CV' => 'Contra'];
                return $map[$log->transaction_type] ?? 'Journal Voucher';
            }
            if ($log->order_id || str_contains($log->source_type ?? '', 'Order')) return 'Sales';
            if (str_contains($log->source_type ?? '', 'Purchase')) return 'Purchase';
            if (str_contains($log->source_type ?? '', 'Supplier')) return 'Purchase';
            if (str_contains($log->source_type ?? '', 'Income')) return 'Cash Receive';
            if (in_array($log->transaction_type, ['opening_balance', 'opening_balance_adjustment'])) return 'Opening Balance';
            return 'Others';
        };

        $sectionOrder = ['Sales', 'Purchase', 'Bank Receive', 'Cash Receive', 'Bank Payment', 'Cash Payment', 'Journal Voucher', 'Contra', 'Opening Balance', 'Others'];

        // Pair debit/credit entries into single rows (group by transaction)
        $paired = [];
        $groups = [];
        foreach ($logs as $log) {
            if ($log->voucher_id) {
                $key = 'v_' . $log->voucher_id;
            } elseif ($log->order_id) {
                $pairGroup = 'revenue';
                $key = 'o_' . $log->order_id . '_' . $pairGroup;
            } elseif ($log->source_id && $log->source_type) {
                $key = 's_' . str_replace('\\', '_', $log->source_type) . '_' . $log->source_id;
            } else {
                $key = 'r_' . $log->reference_no . '_' . $log->transaction_date . '_' . $log->account_id;
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'slip_no' => $log->reference_no,
                    'date' => $log->transaction_date,
                    'from_accounts' => [],
                    'to_accounts' => [],
                    'total_debit' => 0,
                    'total_credit' => 0,
                    'sort_time' => $log->created_at ?? $log->transaction_date,
                    'section' => $getSection($log),
                ];
            }

            if ($log->debit > 0) {
                $groups[$key]['from_accounts'][] = $log->account->name ?? 'N/A';
            }
            if ($log->credit > 0) {
                $groups[$key]['to_accounts'][] = $log->account->name ?? 'N/A';
            }
            $groups[$key]['total_debit'] += $log->debit;
            $groups[$key]['total_credit'] += $log->credit;
        }

        // Build sectioned array
        $sections = [];
        foreach ($sectionOrder as $sectionName) {
            $sections[$sectionName] = [];
        }

        foreach ($groups as $g) {
            $from = implode(', ', array_unique($g['from_accounts']));
            $to = implode(', ', array_unique($g['to_accounts']));

            if (empty($from) && !empty($to)) {
                $from = '-';
            }
            if (empty($to) && !empty($from)) {
                $to = '-';
            }

            $row = (object) [
                'slip_no' => $g['slip_no'],
                'date' => $g['date'],
                'from_account' => $from,
                'to_account' => $to,
                'amount' => $g['total_debit'] > 0 ? $g['total_debit'] : $g['total_credit'],
                'sort_time' => $g['sort_time'],
            ];

            $section = $g['section'] ?? 'Others';
            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }
            $sections[$section][] = $row;
        }

        // Sort rows within each section by sort_time desc
        foreach ($sections as &$rows) {
            usort($rows, function ($a, $b) {
                return strtotime($b->sort_time) - strtotime($a->sort_time);
            });
        }

        // Remove empty sections and flatten for total calculation
        $nonEmptySections = [];
        $paired = [];
        foreach ($sections as $sectionName => $rows) {
            if (count($rows) > 0) {
                $nonEmptySections[$sectionName] = $rows;
                $paired = array_merge($paired, $rows);
            }
        }
        $sections = $nonEmptySections;

        $totalAmount = array_sum(array_column($paired, 'amount'));

        // Cash In (money received - debit to cash/bank accounts)
        $cashIn = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereHas('account', function ($q) {
                $q->where('type', 'asset')->whereIn('sub_type', ['cash', 'bank', 'mobile_bank']);
            })
            ->sum('debit');

        // Cash Out (money paid - credit from cash/bank accounts)
        $cashOut = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereHas('account', function ($q) {
                $q->where('type', 'asset')->whereIn('sub_type', ['cash', 'bank', 'mobile_bank']);
            })
            ->sum('credit');

        $summary = [
            'transaction_count' => count($paired),
            'total_amount' => $totalAmount,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
        ];

        return view('backend.accounts.finance_reports.today_transactions', compact(
            'dateRange',
            'date',
            'startDate',
            'endDate',
            'paired',
            'sections',
            'summary'
        ));
    }

    /**
     * Export Today's Transactions as Excel
     */
    private function exportTodayTransactionsExcel($date, $startDate, $endDate)
    {
        $logs = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereNotIn('transaction_type', ['order_cogs', 'order_inventory', 'courier_settlement_cogs'])
            ->with('account', 'voucher')
            ->orderBy('id', 'desc')
            ->get();

        $getSection = function ($log) {
            if ($log->voucher_id && $log->voucher) {
                $map = ['BP' => 'Bank Payment', 'CP' => 'Cash Payment', 'BR' => 'Bank Receive', 'CR' => 'Cash Receive', 'JV' => 'Journal Voucher', 'CV' => 'Contra'];
                $customMap = ['receipt' => 'Cash Receive', 'payment' => 'Cash Payment'];
                return $customMap[$log->voucher->type] ?? $map[$log->voucher->type] ?? 'Journal Voucher';
            }
            if (str_contains($log->source_type ?? '', 'Voucher')) {
                $map = ['BP' => 'Bank Payment', 'CP' => 'Cash Payment', 'BR' => 'Bank Receive', 'CR' => 'Cash Receive', 'JV' => 'Journal Voucher', 'CV' => 'Contra'];
                return $map[$log->transaction_type] ?? 'Journal Voucher';
            }
            if ($log->order_id || str_contains($log->source_type ?? '', 'Order')) return 'Sales';
            if (str_contains($log->source_type ?? '', 'Purchase')) return 'Purchase';
            if (str_contains($log->source_type ?? '', 'Supplier')) return 'Purchase';
            if (str_contains($log->source_type ?? '', 'Income')) return 'Cash Receive';
            if (in_array($log->transaction_type, ['opening_balance', 'opening_balance_adjustment'])) return 'Opening Balance';
            return 'Others';
        };

        $sectionOrder = ['Sales', 'Purchase', 'Bank Receive', 'Cash Receive', 'Bank Payment', 'Cash Payment', 'Journal Voucher', 'Contra', 'Opening Balance', 'Others'];

        // Pair debit/credit entries (group by transaction)
        $groups = [];
        foreach ($logs as $log) {
            if ($log->voucher_id) {
                $key = 'v_' . $log->voucher_id;
            } elseif ($log->order_id) {
                $pairGroup = 'revenue';
                $key = 'o_' . $log->order_id . '_' . $pairGroup;
            } elseif ($log->source_id && $log->source_type) {
                $key = 's_' . str_replace('\\', '_', $log->source_type) . '_' . $log->source_id;
            } else {
                $key = 'r_' . $log->reference_no . '_' . $log->transaction_date . '_' . $log->account_id;
            }
            if (!isset($groups[$key])) {
                $groups[$key] = ['slip_no' => $log->reference_no, 'from' => [], 'to' => [], 'total_debit' => 0, 'total_credit' => 0, 'section' => $getSection($log)];
            }
            if ($log->debit > 0) {
                $groups[$key]['from'][] = $log->account->name ?? 'N/A';
            }
            if ($log->credit > 0) {
                $groups[$key]['to'][] = $log->account->name ?? 'N/A';
            }
            $groups[$key]['total_debit'] += $log->debit;
            $groups[$key]['total_credit'] += $log->credit;
        }

        // Build sectioned array
        $sections = [];
        foreach ($sectionOrder as $sectionName) {
            $sections[$sectionName] = [];
        }
        foreach ($groups as $g) {
            $from = implode(', ', array_unique($g['from']));
            $to = implode(', ', array_unique($g['to']));
            $row = [
                'slip_no' => $g['slip_no'],
                'from' => empty($from) ? '-' : $from,
                'to' => empty($to) ? '-' : $to,
                'amount' => $g['total_debit'] > 0 ? $g['total_debit'] : $g['total_credit'],
            ];
            $section = $g['section'] ?? 'Others';
            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }
            $sections[$section][] = $row;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(35);
        $sheet->getColumnDimension('E')->setWidth(18);

        $currentRow = 1;

        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", strtoupper(get_setting('site_name', env('APP_NAME', 'Company Name'))));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "TODAY'S TRANSACTION REPORT");
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", "Date: " . Carbon::parse($date)->format('d M Y'));
        $sheet->getStyle("A{$currentRow}")->applyFromArray([
            'font' => ['size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow += 2;

        $paired = [];
        foreach ($sections as $sectionName => $sectionRows) {
            $paired = array_merge($paired, $sectionRows);
        }
        $totalAmount = array_sum(array_column($paired, 'amount'));

        $sheet->setCellValue("A{$currentRow}", "Total Transactions:");
        $sheet->setCellValue("C{$currentRow}", count($paired));
        $currentRow++;
        $sheet->setCellValue("A{$currentRow}", "Total Amount:");
        $sheet->setCellValue("C{$currentRow}", number_format($totalAmount, 2));
        $currentRow += 2;

        // Section header style
        $sectionHeaderStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
        ];
        $sectionTotalStyle = [
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF5']],
        ];
        $headers = ['SL', 'Slip No', 'Paid From Account', 'To', 'Amount (৳)'];
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '333333']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $sl = 1;
        foreach ($sections as $sectionName => $sectionRows) {
            if (count($sectionRows) == 0) continue;

            // Section header row
            $sheet->mergeCells("A{$currentRow}:E{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", strtoupper($sectionName));
            $sheet->getStyle("A{$currentRow}")->applyFromArray($sectionHeaderStyle);
            $currentRow++;

            // Column headers
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . $currentRow, $header);
                $col++;
            }
            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($headerStyle);
            $currentRow++;

            foreach ($sectionRows as $row) {
                $sheet->setCellValue("A{$currentRow}", $sl++);
                $sheet->setCellValue("B{$currentRow}", $row['slip_no']);
                $sheet->setCellValue("C{$currentRow}", $row['from']);
                $sheet->setCellValue("D{$currentRow}", $row['to']);
                $sheet->setCellValue("E{$currentRow}", number_format($row['amount'], 2));

                $bgColor = ($sl % 2 == 0) ? 'F8F9FA' : 'FFFFFF';
                $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
                    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
                ]);
                $currentRow++;
            }

            // Section total
            $sectionTotal = array_sum(array_column($sectionRows, 'amount'));
            $sheet->setCellValue("A{$currentRow}", '');
            $sheet->setCellValue("B{$currentRow}", '');
            $sheet->setCellValue("C{$currentRow}", '');
            $sheet->setCellValue("D{$currentRow}", 'Section Total:');
            $sheet->setCellValue("E{$currentRow}", number_format($sectionTotal, 2));
            $sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($sectionTotalStyle);
            $currentRow++;
        }

        $filename = 'Today_Transactions_' . Carbon::parse($date)->format('d-m-Y') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Export Today's Transactions as PDF
     */
    private function exportTodayTransactionsPDF($date, $startDate, $endDate)
    {
        $logs = AccountStatementLog::whereBetween('transaction_date', [$startDate, $endDate])
            ->whereNotIn('transaction_type', ['order_cogs', 'order_inventory', 'courier_settlement_cogs'])
            ->with('account', 'voucher')
            ->orderBy('id', 'desc')
            ->get();

        $getSection = function ($log) {
            if ($log->voucher_id && $log->voucher) {
                $map = ['BP' => 'Bank Payment', 'CP' => 'Cash Payment', 'BR' => 'Bank Receive', 'CR' => 'Cash Receive', 'JV' => 'Journal Voucher', 'CV' => 'Contra'];
                $customMap = ['receipt' => 'Cash Receive', 'payment' => 'Cash Payment'];
                return $customMap[$log->voucher->type] ?? $map[$log->voucher->type] ?? 'Journal Voucher';
            }
            if (str_contains($log->source_type ?? '', 'Voucher')) {
                $map = ['BP' => 'Bank Payment', 'CP' => 'Cash Payment', 'BR' => 'Bank Receive', 'CR' => 'Cash Receive', 'JV' => 'Journal Voucher', 'CV' => 'Contra'];
                return $map[$log->transaction_type] ?? 'Journal Voucher';
            }
            if ($log->order_id || str_contains($log->source_type ?? '', 'Order')) return 'Sales';
            if (str_contains($log->source_type ?? '', 'Purchase')) return 'Purchase';
            if (str_contains($log->source_type ?? '', 'Supplier')) return 'Purchase';
            if (str_contains($log->source_type ?? '', 'Income')) return 'Cash Receive';
            if (in_array($log->transaction_type, ['opening_balance', 'opening_balance_adjustment'])) return 'Opening Balance';
            return 'Others';
        };

        $sectionOrder = ['Sales', 'Purchase', 'Bank Receive', 'Cash Receive', 'Bank Payment', 'Cash Payment', 'Journal Voucher', 'Contra', 'Opening Balance', 'Others'];

        $groups = [];
        foreach ($logs as $log) {
            if ($log->voucher_id) {
                $key = 'v_' . $log->voucher_id;
            } elseif ($log->order_id) {
                $pairGroup = 'revenue';
                $key = 'o_' . $log->order_id . '_' . $pairGroup;
            } elseif ($log->source_id && $log->source_type) {
                $key = 's_' . str_replace('\\', '_', $log->source_type) . '_' . $log->source_id;
            } else {
                $key = 'r_' . $log->reference_no . '_' . $log->transaction_date . '_' . $log->account_id;
            }
            if (!isset($groups[$key])) {
                $groups[$key] = ['slip_no' => $log->reference_no, 'from' => [], 'to' => [], 'total_debit' => 0, 'total_credit' => 0, 'section' => $getSection($log)];
            }
            if ($log->debit > 0) {
                $groups[$key]['from'][] = $log->account->name ?? 'N/A';
            }
            if ($log->credit > 0) {
                $groups[$key]['to'][] = $log->account->name ?? 'N/A';
            }
            $groups[$key]['total_debit'] += $log->debit;
            $groups[$key]['total_credit'] += $log->credit;
        }

        $sections = [];
        foreach ($sectionOrder as $sectionName) {
            $sections[$sectionName] = [];
        }
        foreach ($groups as $g) {
            $from = implode(', ', array_unique($g['from']));
            $to = implode(', ', array_unique($g['to']));
            $row = (object)[
                'slip_no' => $g['slip_no'],
                'from' => empty($from) ? '-' : $from,
                'to' => empty($to) ? '-' : $to,
                'amount' => $g['total_debit'] > 0 ? $g['total_debit'] : $g['total_credit'],
            ];
            $section = $g['section'] ?? 'Others';
            if (!isset($sections[$section])) {
                $sections[$section] = [];
            }
            $sections[$section][] = $row;
        }

        $paired = [];
        foreach ($sections as $sectionName => $sectionRows) {
            if (count($sectionRows) > 0) {
                $paired = array_merge($paired, $sectionRows);
            }
        }
        $totalAmount = array_sum(array_column($paired, 'amount'));
        $data = compact('date', 'paired', 'sections', 'totalAmount');
        $filename = 'Today_Transactions_' . Carbon::parse($date)->format('d-m-Y') . '.pdf';

        try {
            $html = view('backend.accounts.finance_reports.pdf.today_transactions_pdf', $data)->render();

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'default_font' => 'freeserif',
            ]);

            $mpdf->SetTitle("Today's Transaction Report");
            $mpdf->WriteHTML($html);
            return $mpdf->Output($filename, 'D');
        } catch (\Exception $e) {
            \Log::error('PDF generation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'PDF generation failed: ' . $e->getMessage());
        }
    }


    /**
     * Get account balance for a specific period
     */
    private function getAccountBalanceForPeriod($accountId, $startDate, $endDate, $type = 'debit')
    {
        $query = AccountStatementLog::where('account_id', $accountId)
            ->whereBetween('transaction_date', [$startDate, $endDate]);

        if ($type === 'debit') {
            return $query->sum('debit') - $query->sum('credit');
        } else {
            return $query->sum('credit') - $query->sum('debit');
        }
    }

    /**
     * Get account balance before a specific date
     */
    private function getAccountBalanceBeforeDate($accountId, $date)
    {
        $lastLog = AccountStatementLog::where('account_id', $accountId)
            ->where('transaction_date', '<', $date)
            ->orderBy('id', 'desc')
            ->first();

        return $lastLog ? $lastLog->balance : 0;
    }
}
