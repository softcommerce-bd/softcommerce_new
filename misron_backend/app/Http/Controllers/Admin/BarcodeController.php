<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BarcodeController extends Controller
{
    public function index()
    {
        return view('backend.barcode.index');
    }

    // public function search(Request $request)
    // {
    //     $query = $request->input('query');
    //     $products = Product::where('name', 'LIKE', "%{$query}%")->get();

    //     $output = '';
    //     foreach ($products as $product) {
    //         $output .= '<div class="search-result-item" data-product="' . htmlspecialchars(json_encode([
    //             'id' => $product->id,
    //             'name' => $product->name,
    //             'barcode' => $product->barcode ?? $product->id,
    //             'unit_price' => $product->unit_price,
    //             'discount' => $product->discount,
    //             'discount_type' => $product->discount_type,
    //             'discount_start_date' => $product->discount_start_date,
    //             'discount_end_date' => $product->discount_end_date
    //         ])) . '">';
    //         $output .= $product->name;
    //         $output .= '</div>';
    //     }

    //     return $output;
    // }
      public function search(Request $request)
{
    $query = $request->input('query');
    $keyword = trim($query);

    if (is_numeric($keyword)) {
        // Search by exact product ID, name, or barcode
        $products = Product::where(function ($q) use ($keyword) {
            $q->where('id', $keyword)
              ->orWhere('name', 'like', '%' . $keyword . '%')
              ->orWhere('barcode', $keyword);
        })->get();
    } else {
        // Search by name or barcode only
        $products = Product::where(function ($q) use ($keyword) {
            $q->where('name', 'like', '%' . $keyword . '%')
              ->orWhere('barcode', $keyword);
        })->get();
    }

    $output = '';
    foreach ($products as $product) {
        $output .= '<div class="search-result-item" data-product="' . htmlspecialchars(json_encode([
            'id' => $product->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode ?? $product->id,
            'unit_price' => $product->unit_price,
            'discount' => $product->discount,
            'discount_type' => $product->discount_type,
            'discount_start_date' => $product->discount_start_date,
            'discount_end_date' => $product->discount_end_date
        ])) . '">';
        $output .= '<strong>#' . $product->id . '</strong> - ' . $product->name;
        $output .= '</div>';
    }

    return $output;
}

    public function generate(Request $request)
    {
        $products = $request->input('products');
        $barcodeSetting = $request->input('barcode_setting');

        $pageSize = $this->getPageSize($barcodeSetting);
        $isContinuousRoll = ($barcodeSetting == '6');

        $barcodeHtml = '';

      if ($isContinuousRoll) {
        // Continuous roll - EXACT ONE PAGE PER LABEL
        foreach ($products as $productData) {
            $unitPrice = (float) $productData['unit_price'];
            $discountedPrice = $this->calculateDiscountedPrice($productData);
            
            for ($i = 0; $i < $productData['quantity']; $i++) {
                // Each label in its own page div
                $barcodeHtml .= '
                <div class="continuous-roll-page" style="
                    width: 31.75mm;
                    height: 25.4mm;
                    margin: 0;
                    padding: 0px;
                    page-break-after: always;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    ' . $this->generateBarcode(
                        $productData['id'],
                        $productData['name'],
                        $productData['barcode'],
                        $unitPrice,
                        $discountedPrice,
                        $isContinuousRoll
                    ) . '
                </div>';
            }
        }
    } else {
            // Sheet paper format
            $labelsPerSheet = $pageSize['labels_per_sheet'];
            $totalLabels = array_sum(array_column($products, 'quantity'));
            $pages = ceil($totalLabels / $labelsPerSheet);

            // Determine layout
            if ($labelsPerSheet <= 20) {
                $columns = 2;
            } elseif ($labelsPerSheet <= 30) {
                $columns = 3;
            } elseif ($labelsPerSheet <= 40) {
                $columns = 4;
            } else {
                $columns = 5;
            }

            $rows = ceil($labelsPerSheet / $columns);

            for ($page = 0; $page < $pages; $page++) {
                $barcodeHtml .= '<div class="barcode-page sheet-page">';
                $barcodeHtml .= '<div class="barcode-print-wrapper">';

                $labelsOnPage = min($labelsPerSheet, $totalLabels - ($page * $labelsPerSheet));

                // Generate labels for this page
                $labelsGenerated = 0;
                foreach ($products as $productData) {
                    // Calculate discounted price for this product
                    $unitPrice = (float) $productData['unit_price'];
                    $discountedPrice = $this->calculateDiscountedPrice($productData);
                    
                    for ($q = 0; $q < $productData['quantity']; $q++) {
                        if ($labelsGenerated >= $labelsOnPage) {
                            break 2;
                        }

                        $barcodeHtml .= $this->generateBarcode(
                            $productData['id'],
                            $productData['name'],
                            $productData['barcode'],
                            $unitPrice,
                            $discountedPrice,
                            $isContinuousRoll
                        );

                        $labelsGenerated++;
                    }
                }

                $barcodeHtml .= '</div>';
                $barcodeHtml .= '</div>';
            }
        }

        return response()->json([
            'html' => $barcodeHtml,
            'labelsPerSheet' => $isContinuousRoll ? 1 : $pageSize['labels_per_sheet'],
            'isContinuousRoll' => $isContinuousRoll
        ]);
    }

    private function calculateDiscountedPrice($productData)
    {
        $unitPrice = (float) $productData['unit_price'];
        
        // Check if product has discount data
        if (isset($productData['discount']) && $productData['discount'] > 0) {
            $discount = (float) $productData['discount'];
            $discountType = $productData['discount_type'] ?? 'percent';
            
            // Check if discount is active based on dates
            $isDiscountActive = true;
            if (isset($productData['discount_start_date']) && isset($productData['discount_end_date'])) {
                $now = time();
                $startDate = strtotime($productData['discount_start_date']);
                $endDate = strtotime($productData['discount_end_date']);
                
                if ($startDate && $endDate) {
                    $isDiscountActive = ($now >= $startDate && $now <= $endDate);
                }
            }
            
            if ($isDiscountActive) {
                if ($discountType == 'percent') {
                    return $unitPrice - ($unitPrice * $discount / 100);
                } else {
                    // amount discount
                    return $unitPrice - $discount;
                }
            }
        }
        
        // If no discount, return original price
        return $unitPrice;
    }

    private function generateBarcode(
        $productId,
        $productName,
        $barcode,
        $unitPrice,
        $discountedPrice = null,
        $isContinuousRoll = false
     ) {
        // If barcode is null or empty, use product ID as fallback
        if (empty($barcode) || $barcode === 'null' || $barcode === null) {
            $barcode = (string) $productId;
        }
    
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $barcodeImage = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);
        $base64Barcode = base64_encode($barcodeImage);
    
        // Format prices
        $unitPrice = (float) $unitPrice;
        $discountedPrice = $discountedPrice ? (float) $discountedPrice : $unitPrice;
        $hasDiscount = $discountedPrice < $unitPrice;
        $siteName = get_setting('site_name') ?? config('app.name', 'STORE');
    
        if ($isContinuousRoll) {
            // ULTRA-COMPACT CONTINUOUS ROLL DESIGN (31.75mm × 25.4mm)
            $priceHtml = '';
            
            if ($hasDiscount) {
                $priceHtml = '
                    <div style="margin: 0; line-height: 1; padding:0;">
                    <span style="font-size: 5pt; color: #000; margin-top:0px; margin-bottom:0px;">Price: </span>
                        <span style="font-size: 5pt;
                        color: #000;
                        text-decoration-line: line-through;
                        text-decoration-thickness: 0.2px;
                        margin-right: 1mm;
                        margin-top: 0;
                        margin-bottom: 0;">
                            ' . number_format($unitPrice, 0) . '/-
                        </span>
                        <span style="font-size: 5pt; font-weight: bold; color: #000; margin-top:0px; margin-bottom:0px;">
                            ' . number_format($discountedPrice, 0) . '/-
                        </span>
                    </div>
                ';
            } else {
                $priceHtml = '
                    <div style="font-size: 5pt; font-weight: bold; color: #000; margin: 0; line-height: 1; margin-top:0px; margin-bottom:0px;">
                    <span style="font-size: 5pt; color: #000;">Price: </span>
                        ' . number_format($unitPrice, 0) . '/-
                    </div>
                ';
            }
    
            return '
                <div style="
                    width: 32.75mm;
                    height: 25.4mm;
                    padding: 3.5mm .5mm;
                    box-sizing: border-box;
                    font-family: Arial, sans-serif;
                    text-align: center;
                    color: #000;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    align-items: center;
                    page-break-inside: avoid;
                    overflow: hidden;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                    margin-left: 0 !important;
                ">
                <!-- Store Name - TOP LINE -->
                <div style="
                    font-size: 9pt;
                    font-weight: 900;
                    text-transform: uppercase;
                    letter-spacing: 0.3px;
                    line-height: 1;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    width: 100%;
                ">
                    ' . htmlspecialchars($siteName) . '
                </div>
    
              <!-- Product Name -->
            <div style="
                font-size: 9px;
                font-weight: 600;
                color: #333;
                margin-bottom: 0px;
                padding-top: 2px;
                line-height: 1.1;
                // height: 28px;
                overflow: hidden;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
            ">
                ' . htmlspecialchars($productName) . '
            </div>
    
    
                <!-- Price - COMPACT -->
                <div style="margin: 0; line-height: 1; padding:0;">
                    ' . $priceHtml . '
                </div>
    
                <!-- Barcode Image - SMALLER -->
                <div style="
                    height: 6mm;
                    margin:0;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <img src="data:image/png;base64,' . $base64Barcode . '"
                        alt="' . htmlspecialchars($barcode) . '"
                        style="
                            width: 100%;
                            height: 100%;
                            object-fit: contain;
                            image-rendering: crisp-edges;
                        ">
                </div>
    
                <!-- Barcode Number - BOTTOM LINE -->
                <div style="
                    font-size: 5pt;
                    font-weight: bold;
                    letter-spacing: 0.3px;
                    line-height: 1;
                    color: #000;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    width: 100%;
                    margin: 0;
                ">
                    ' . htmlspecialchars($barcode) . '
                </div>
            </div>';
        }
    
        // SHEET PAPER DESIGN (same as before with minor adjustments)
        $priceHtml = '';
        
        if ($hasDiscount) {
            $priceHtml = '
                <div style="display: flex; flex-direction: column; align-items: center; gap: 0; margin: 0;">
                 <span style="font-size: 5pt; color: #000;">Price: </span>
                    
                    <span style="font-size: 5pt; color: #000; text-decoration-line: line-through;
                        text-decoration-thickness: 0.2px; line-height: 1;">
                        ' . number_format($unitPrice, 0) . '
                    </span>
                    <span style="font-size: 5pt; color: #000; line-height: 1;">
                        ' . number_format($discountedPrice, 0) . '/-
                    </span>
                </div>
            ';
        } else {
            $priceHtml = '
                <div style="font-size: 5pt; font-weight: bold; color: #000; line-height: 1; margin: 0;">
                 <span style="font-size: 5pt; color: #000;"> Price: </span>
                    ' . number_format($unitPrice, 0) . '/-
                </div>
            ';
        }
    
        return '
        <div style="
            border: 1px solid #ddd;
            border-radius: 2px;
            background: #ffffff;
            // padding: 3px 2px;
            text-align: center;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            width: 100%;
            page-break-inside: avoid;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        ">
            <!-- Store Name -->
            <div style="
                font-size: 12px;
                font-weight: 900;
                text-transform: uppercase;
                color: #000;
                margin: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                width: 100%;
                letter-spacing: 0.3px;
            ">
                ' . htmlspecialchars($siteName) . '
            </div>
    
            <!-- Product Name -->
            <div style="
                font-size: 10px;
                font-weight: 600;
                color: #333;
                padding-top: 2px;
                line-height: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                width: 100%;
                // max-height: 20px;
            ">
                ' . htmlspecialchars($productName) . '
            </div>
    
            <!-- Price -->
            <div style="margin: 0; padding:0;">
                ' . $priceHtml . '
            </div>
    
            <!-- Barcode Image -->
            <div style="
                width: 100%;
                height: 18px;
                margin: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <img src="data:image/png;base64,' . $base64Barcode . '"
                    alt="' . htmlspecialchars($barcode) . '"
                    style="
                        max-width: 100%;
                        height: 100%;
                        object-fit: contain;
                    " />
            </div>
    
            <!-- Barcode Number -->
            <div style="
                font-size: 6px;
                font-weight: bold;
                color: #555;
                letter-spacing: 0.3px;
                line-height: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                width: 100%;
                margin: 0;
            ">
                ' . htmlspecialchars($barcode) . '
            </div>
        </div>';
    }

    private function getPageSize($barcodeSetting)
    {
        switch ($barcodeSetting) {
            case '1':
                return ['width' => 8.5, 'height' => 11, 'labels_per_sheet' => 20];
            case '2':
                return ['width' => 8.5, 'height' => 11, 'labels_per_sheet' => 30];
            case '3':
                return ['width' => 8.5, 'height' => 11, 'labels_per_sheet' => 32];
            case '4':
                return ['width' => 8.5, 'height' => 11, 'labels_per_sheet' => 40];
            case '5':
                return ['width' => 8.5, 'height' => 11, 'labels_per_sheet' => 50];
            case '6':
                return ['width' => 3.175, 'height' => 2.54, 'labels_per_sheet' => 1];
            default:
                return ['width' => 8.5, 'height' => 11, 'labels_per_sheet' => 20];
        }
    }
}