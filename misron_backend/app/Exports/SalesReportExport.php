<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $orders;
    protected $totalSale;
    protected $totalOrder;
    protected $totalBuyingCost;
    protected $totalProfit;
    protected $totalDiscount;
    protected $totalSubtotal;

    public function __construct($orders, $totalSale, $totalOrder, $totalBuyingCost, $totalProfit, $totalDiscount = 0, $totalSubtotal = 0, $totaldeliveryCharge = 0)
    {
        $this->orders = $orders;
        $this->totalSale = $totalSale;
        $this->totalOrder = $totalOrder;
        $this->totalBuyingCost = $totalBuyingCost;
        $this->totalProfit = $totalProfit;
        $this->totalDiscount = $totalDiscount;
        $this->totalSubtotal = $totalSubtotal;
        $this->totaldeliveryCharge = $totaldeliveryCharge;
    }

    public function collection()
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return [
            'Order ID',
            'Order Code',
            'Order Date',
            'Customer Name',
            'Product Names',
            'Total Items',
            'Payment Status',
            'Delivery Status',
            'Subtotal (without Shipping cost)',
            'Coupon Discount',
            'Total Sale (After Discount)',
            'Total Buying Cost',
            'Profit',
            'Delivery Charge'
        ];
    }

    public function map($order): array
    {
        // Handle customer name safely (matching salesReport logic)
        $customerName = 'Guest';
        if ($order->user && $order->user->name) {
            $customerName = $order->user->name;
        } elseif ($order->shipping_address) {
            $shippingData = json_decode($order->shipping_address, true);
            if (is_array($shippingData)) {
                $customerName = $shippingData['name'] ?? $shippingData['first_name'] ?? $shippingData['customer_name'] ?? 'Guest';
            } elseif (is_string($order->shipping_address)) {
                $customerName = $order->shipping_address;
            }
        }
        
        // Get product names with quantities
        $productNames = [];
        foreach ($order->orderDetails as $orderDetail) {
            $productName = $orderDetail->product->getTranslation('name') ?? $orderDetail->product->name ?? 'N/A';
            $quantity = $orderDetail->quantity;
            $productNames[] = $productName . ' (x' . $quantity . ')';
        }
        $productNamesString = implode(', ', $productNames);
        
        // Get delivery charge (shipping_cost from first order detail)
        $deliveryCharge = $order->orderDetails->first()->shipping_cost ?? 0;
        
        return [
            $order->id,
            $order->code,
            date('d M Y', strtotime($order->created_at)),
            $customerName,
            $productNamesString,
            $order->orderDetails->count(),
            ucfirst($order->payment_status),
            ucfirst($order->delivery_status),
            $order->calculated_product_sales ?? 0,    // Subtotal before discount
            $order->calculated_discount ?? 0,          // Coupon discount amount
            $order->calculated_net_sale ?? $order->grand_total,  // Total after discount
            $order->calculated_buying_cost ?? 0,       // Buying cost
            $order->calculated_profit ?? 0,            // Profit
            $deliveryCharge
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Add header styles
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        $sheet->getStyle('A1:N1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $sheet->getStyle('A1:N1')->getFill()->getStartColor()->setARGB('FFE0E0E0');
        
        // Set column E (Product Names) to wrap text
        $sheet->getStyle('E:E')->getAlignment()->setWrapText(true);
        
        // Set number format for currency columns (I, J, K, L, M, N)
        $sheet->getStyle('I:N')->getNumberFormat()->setFormatCode('#,##0.00');
        
        // Color the discount column (J) in red
        $sheet->getStyle('J:J')->getFont()->getColor()->setARGB('FFFF0000');
        
        // MANUALLY SET COLUMN WIDTHS
        $sheet->getColumnDimension('A')->setWidth(12);  // Order ID
        $sheet->getColumnDimension('B')->setWidth(12);  // Order Code
        $sheet->getColumnDimension('C')->setWidth(15);  // Order Date
        $sheet->getColumnDimension('D')->setWidth(25);  // Customer Name
        $sheet->getColumnDimension('E')->setWidth(60);  // Product Names (WIDE)
        $sheet->getColumnDimension('F')->setWidth(12);  // Total Items
        $sheet->getColumnDimension('G')->setWidth(15);  // Payment Status
        $sheet->getColumnDimension('H')->setWidth(15);  // Delivery Status
        $sheet->getColumnDimension('I')->setWidth(22);  // Subtotal
        $sheet->getColumnDimension('J')->setWidth(18);  // Coupon Discount
        $sheet->getColumnDimension('K')->setWidth(22);  // Total Sale
        $sheet->getColumnDimension('L')->setWidth(20);  // Total Buying Cost
        $sheet->getColumnDimension('M')->setWidth(15);  // Profit
        $sheet->getColumnDimension('N')->setWidth(18);  // Delivery Charge
        
        // Add total row
        $totalRow = count($this->orders) + 3;
        
        // Add separator line
        $sheet->setCellValue('A' . $totalRow, str_repeat('=', 50));
        $sheet->mergeCells('A' . $totalRow . ':N' . $totalRow);
        
        // Add summary header
        $sheet->setCellValue('A' . ($totalRow + 1), 'SUMMARY REPORT');
        $sheet->mergeCells('A' . ($totalRow + 1) . ':N' . ($totalRow + 1));
        $sheet->getStyle('A' . ($totalRow + 1))->getFont()->setBold(true)->setSize(12);
        
        // Summary rows
        $summaryStartRow = $totalRow + 2;
        
        $sheet->getColumnDimension('A')->setWidth(25);  // Increase width for labels column
        $sheet->getColumnDimension('B')->setWidth(20);  // Increase width for values column
        
        $sheet->setCellValue('A' . $summaryStartRow, 'Total Orders:');
        $sheet->setCellValue('B' . $summaryStartRow, $this->totalOrder);
        $sheet->getStyle('A' . $summaryStartRow)->getFont()->setBold(true);
        
        $sheet->setCellValue('A' . ($summaryStartRow + 1), 'Total Subtotal:');
        $sheet->setCellValue('B' . ($summaryStartRow + 1), $this->totalSubtotal);
        $sheet->getStyle('A' . ($summaryStartRow + 1))->getFont()->setBold(true);
        $sheet->getStyle('B' . ($summaryStartRow + 1))->getNumberFormat()->setFormatCode('#,##0.00');
        
        $sheet->setCellValue('A' . ($summaryStartRow + 2), 'Total Discount:');
        $sheet->setCellValue('B' . ($summaryStartRow + 2), $this->totalDiscount);
        $sheet->getStyle('A' . ($summaryStartRow + 2))->getFont()->setBold(true);
        $sheet->getStyle('A' . ($summaryStartRow + 2))->getFont()->getColor()->setARGB('FFFF0000');
        $sheet->getStyle('B' . ($summaryStartRow + 2))->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('B' . ($summaryStartRow + 2))->getFont()->getColor()->setARGB('FFFF0000');
        
        $sheet->setCellValue('A' . ($summaryStartRow + 3), 'Total Sale (After Discount & Without delivery charge):');
        $sheet->setCellValue('B' . ($summaryStartRow + 3), $this->totalSale);
        $sheet->getStyle('A' . ($summaryStartRow + 3))->getFont()->setBold(true);
        $sheet->getStyle('B' . ($summaryStartRow + 3))->getNumberFormat()->setFormatCode('#,##0.00');
        
        $sheet->setCellValue('A' . ($summaryStartRow + 4), 'Total Buying Cost:');
        $sheet->setCellValue('B' . ($summaryStartRow + 4), $this->totalBuyingCost);
        $sheet->getStyle('A' . ($summaryStartRow + 4))->getFont()->setBold(true);
        $sheet->getStyle('B' . ($summaryStartRow + 4))->getNumberFormat()->setFormatCode('#,##0.00');
        
        $sheet->setCellValue('A' . ($summaryStartRow + 5), 'Total Delivery Cost:');
        $sheet->setCellValue('B' . ($summaryStartRow + 5), $this->totaldeliveryCharge);
        $sheet->getStyle('A' . ($summaryStartRow + 5))->getFont()->setBold(true);
        $sheet->getStyle('B' . ($summaryStartRow + 5))->getNumberFormat()->setFormatCode('#,##0.00');
        
        $sheet->setCellValue('A' . ($summaryStartRow + 6), 'Total Profit:');
        $sheet->setCellValue('B' . ($summaryStartRow + 6), $this->totalProfit);
        $sheet->getStyle('A' . ($summaryStartRow + 6))->getFont()->setBold(true);
        
        // Set profit color based on value
        if ($this->totalProfit >= 0) {
            $sheet->getStyle('B' . ($summaryStartRow + 6))->getFont()->getColor()->setARGB('FF008000'); // Green
        } else {
            $sheet->getStyle('B' . ($summaryStartRow + 6))->getFont()->getColor()->setARGB('FFFF0000'); // Red
        }
        $sheet->getStyle('B' . ($summaryStartRow + 6))->getNumberFormat()->setFormatCode('#,##0.00');
        
        // Add a note about coupon discount
        $sheet->setCellValue('A' . ($summaryStartRow + 7), 'Note:');
        $sheet->setCellValue('B' . ($summaryStartRow + 7), 'Coupon discount reduces the Total Sale amount from Subtotal');
        $sheet->mergeCells('B' . ($summaryStartRow + 7) . ':N' . ($summaryStartRow + 7));
        $sheet->getStyle('A' . ($summaryStartRow + 7) . ':N' . ($summaryStartRow + 7))->getFont()->setItalic(true);
        $sheet->getStyle('A' . ($summaryStartRow + 7) . ':N' . ($summaryStartRow + 7))->getFont()->getColor()->setARGB('FF808080');
        
        // Style the summary section
        $sheet->getStyle('A' . ($totalRow + 1) . ':B' . ($summaryStartRow + 5))->getFont()->setBold(true);
        
        // Add borders to summary section
        $sheet->getStyle('A' . ($totalRow + 1) . ':N' . ($summaryStartRow + 5))->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Add background color to summary header
        $sheet->getStyle('A' . ($totalRow + 1) . ':N' . ($totalRow + 1))->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF4472C4');
        $sheet->getStyle('A' . ($totalRow + 1) . ':N' . ($totalRow + 1))->getFont()->getColor()->setARGB('FFFFFFFF');
        
        // Freeze the header row
        $sheet->freezePane('A2');
        
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}