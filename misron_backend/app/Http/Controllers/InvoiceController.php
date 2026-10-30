<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Language;
use App\Utility\FontUtility;
use App\Models\Order;
use Session;
use PDF;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Config;

class InvoiceController extends Controller
{
    //download invoice
    public function invoice_download($id)
    {
        if (Session::has('currency_code')) {
            $currency_code = Session::get('currency_code');
        } else {
            $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
        }
        $language_code = Session::get('locale', Config::get('app.locale'));

        if (Language::where('code', $language_code)->first()->rtl == 1) {
            $direction = 'rtl';
            $text_align = 'right';
            $not_text_align = 'left';
        } else {
            $direction = 'ltr';
            $text_align = 'left';
            $not_text_align = 'right';
        }

        if (
            $currency_code == 'BDT' ||
            $language_code == 'bd'
        ) {
            // bengali font
            $font_family = "'Hind Siliguri','freeserif'";
        } elseif (
            $currency_code == 'KHR' ||
            $language_code == 'kh'
        ) {
            // khmer font
            $font_family = "'Hanuman','sans-serif'";
        } elseif ($currency_code == 'AMD') {
            // Armenia font
            $font_family = "'arnamu','sans-serif'";
            // }elseif($currency_code == 'ILS'){
            //     // Israeli font
            //     $font_family = "'Varela Round','sans-serif'";
        } elseif (
            $currency_code == 'AED' ||
            $currency_code == 'EGP' ||
            $language_code == 'sa' ||
            $currency_code == 'IQD' ||
            $language_code == 'ir' ||
            $language_code == 'om' ||
            $currency_code == 'ROM' ||
            $currency_code == 'SDG' ||
            $currency_code == 'ILS' ||
            $language_code == 'jo'
        ) {
            // middle east/arabic/Israeli font
            $font_family = "xbriyaz";
        } elseif ($currency_code == 'THB') {
            // thai font
            $font_family = "'Kanit','sans-serif'";
        } elseif (
            $currency_code == 'CNY' ||
            $language_code == 'zh'
        ) {
            // Chinese font
            $font_family = "'sun-exta','gb'";
        } elseif (
            $currency_code == 'MMK' ||
            $language_code == 'mm'
        ) {
            // Myanmar font
            $font_family = 'tharlon';
        } elseif (
            $currency_code == 'THB' ||
            $language_code == 'th'
        ) {
            // Thai font
            $font_family = "'zawgyi-one','sans-serif'";
        } elseif (
            $currency_code == 'USD'
        ) {
            // Thai font
            $font_family = "'Roboto','sans-serif'";
        } else {
            // general for all
            $font_family = "freeserif";
        }

        // $config = ['instanceConfigurator' => function($mpdf) {
        //     $mpdf->showImageErrors = true;
        // }];
        // mpdf config will be used in 4th params of loadview

        $config = [];

        $order = Order::findOrFail($id);
        return PDF::loadView('backend.invoices.invoice', [
            'order' => $order,
            'font_family' => $font_family,
            'direction' => $direction,
            'text_align' => $text_align,
            'not_text_align' => $not_text_align
        ], [], $config)->stream('order-' . $order->code . '.pdf');
    }

 
    // public function paySlip($id)
    // {
    //     if (Session::has('currency_code')) {
    //         $currency_code = Session::get('currency_code');
    //     } else {
    //         $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
    //     }
    //     $language_code = Session::get('locale', Config::get('app.locale'));

    //     if (Language::where('code', $language_code)->first()->rtl == 1) {
    //         $direction = 'rtl';
    //         $text_align = 'right';
    //         $not_text_align = 'left';
    //     } else {
    //         $direction = 'ltr';
    //         $text_align = 'left';
    //         $not_text_align = 'right';
    //     }

    //     if (
    //         $currency_code == 'BDT' ||
    //         $language_code == 'bd'
    //     ) {
    //         $font_family = "'Hind Siliguri','freeserif'";
    //     } elseif (
    //         $currency_code == 'KHR' ||
    //         $language_code == 'kh'
    //     ) {
    //         $font_family = "'Hanuman','sans-serif'";
    //     } elseif ($currency_code == 'AMD') {
    //         $font_family = "'arnamu','sans-serif'";
    //     } elseif (
    //         $currency_code == 'AED' ||
    //         $currency_code == 'EGP' ||
    //         $language_code == 'sa' ||
    //         $currency_code == 'IQD' ||
    //         $language_code == 'ir' ||
    //         $language_code == 'om' ||
    //         $currency_code == 'ROM' ||
    //         $currency_code == 'SDG' ||
    //         $currency_code == 'ILS' ||
    //         $language_code == 'jo'
    //     ) {
    //         // middle east/arabic/Israeli font
    //         $font_family = "xbriyaz";
    //     } elseif ($currency_code == 'THB') {
    //         // thai font
    //         $font_family = "'Kanit','sans-serif'";
    //     } elseif (
    //         $currency_code == 'CNY' ||
    //         $language_code == 'zh'
    //     ) {
    //         // Chinese font
    //         $font_family = "'sun-exta','gb'";
    //     } elseif (
    //         $currency_code == 'MMK' ||
    //         $language_code == 'mm'
    //     ) {
    //         // Myanmar font
    //         $font_family = 'tharlon';
    //     } elseif (
    //         $currency_code == 'THB' ||
    //         $language_code == 'th'
    //     ) {
    //         // Thai font
    //         $font_family = "'zawgyi-one','sans-serif'";
    //     } elseif (
    //         $currency_code == 'USD'
    //     ) {
    //         // Thai font
    //         $font_family = "'Roboto','sans-serif'";
    //     } else {
    //         // general for all
    //         $font_family = "freeserif";
    //     }

    //     // $config = ['instanceConfigurator' => function($mpdf) {
    //     //     $mpdf->showImageErrors = true;
    //     // }];
    //     // mpdf config will be used in 4th params of loadview

    //     $config = [];
        
    //     $order = Order::findOrFail($id);
        
    //     $print_width = get_setting('print_width');
    //         if ($print_width == null) {
    //             flash(translate('Thermal printer size is not given in POS configuration'))->warning();
    //             return back();
    //         }
    
    //         $pdf_style_data = FontUtility::get_font_family();
    
    //         $html = view('backend.sales.thermal_invoice_new', compact('order', 'pdf_style_data', 'print_width', 'font_family'));
    //     //   return $html;
    //         $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => [$print_width, 1000]]);
    //         $mpdf->WriteHTML($html);
    //         // $mpdf->WriteHTML('<h1>Hello world!</h1>');
    //         $mpdf->page   = 0;
    //         $mpdf->state  = 0;
    //         unset($mpdf->pages[0]);
    //         // The $p needs to be passed by reference
    //         $p = 'P';
    //         // dd($mpdf->y);
    //         $mpdf->_setPageSize(array($print_width, $mpdf->y), $p);
    
    //         // $mpdf->addPage();
    //         $mpdf->WriteHTML($html);
    
    //         $mpdf->Output('order-' . $order->code . '.pdf', 'I');

    //     // $order = Order::findOrFail($id);
    //     // return  PDF::loadView('backend.sales.payslip', [
    //     //     'order' => $order,
    //     //     'font_family' => $font_family,
    //     //     'direction' => $direction,
    //     //     'text_align' => $text_align,
    //     //     'not_text_align' => $not_text_align
    //     // ], [], $config)->download('payslip-' . $order->code . '.pdf');

    // }
    
    public function paySlip($id)
{
    // Currency + language
    if (Session::has('currency_code')) {
        $currency_code = Session::get('currency_code');
    } else {
        $currency_code = Currency::findOrFail(get_setting('system_default_currency'))->code;
    }
    $language_code = Session::get('locale', Config::get('app.locale'));

    if (Language::where('code', $language_code)->first()->rtl == 1) {
        $direction = 'rtl';
        $text_align = 'right';
        $not_text_align = 'left';
    } else {
        $direction = 'ltr';
        $text_align = 'left';
        $not_text_align = 'right';
    }

    // Font family selection
    if ($currency_code == 'BDT' || $language_code == 'bd') {
        $font_family = "'Hind Siliguri','freeserif'";
    } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
        $font_family = "'Hanuman','sans-serif'";
    } elseif ($currency_code == 'AMD') {
        $font_family = "'arnamu','sans-serif'";
    } elseif (
        in_array($currency_code, ['AED', 'EGP', 'IQD', 'ROM', 'SDG', 'ILS']) ||
        in_array($language_code, ['sa', 'ir', 'om', 'jo'])
    ) {
        $font_family = "xbriyaz";
    } elseif ($currency_code == 'THB' || $language_code == 'th') {
        $font_family = "'Kanit','sans-serif'";
    } elseif ($currency_code == 'CNY' || $language_code == 'zh') {
        $font_family = "'sun-exta','gb'";
    } elseif ($currency_code == 'MMK' || $language_code == 'mm') {
        $font_family = 'tharlon';
    } elseif ($currency_code == 'USD') {
        $font_family = "'Roboto','sans-serif'";
    } else {
        $font_family = "freeserif";
    }

    // Load order
    $order = Order::findOrFail($id);

    // Printer width (mm)
    $print_width = get_setting('print_width');
    if ($print_width == null) {
        flash(translate('Thermal printer size is not given in POS configuration'))->warning();
        return back();
    }

    $pdf_style_data = FontUtility::get_font_family();

// dd($font_family, $pdf_style_data);
    // Load HTML
    $html = view('backend.sales.thermal_invoice_new', compact(
        'order',
        'pdf_style_data',
        'print_width',
        'font_family'
    ));
    // return $html;
    $defaultConfig = (new ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    
    $defaultFontConfig = (new FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

     $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => [$print_width, 110.6],
        'margin_left'   => 5,
        'margin_right'  => 5,
        'margin_top'    => 5,
        'margin_bottom' => 5,
        'fontDir' => array_merge($fontDirs, [
            resource_path('fonts'),
        ]),
        'fontdata' => $fontData + [
            'solaiman' => [
                'R' => 'SolaimanLipi.ttf',
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ],
        ],

        'default_font' => 'solaiman',
    ]);

    // efine fixed custom paper size (width = thermal, height = auto 100mm or more)
    // $mpdf = new \Mpdf\Mpdf([
    //     'mode' => 'utf-8',
    //     'format' => [$print_width, 101.6], // 3x4 inch = 76.2 x 101.6 mm
    //     'margin_left'   => 5,
    //     'margin_right'  => 5,
    //     'margin_top'    => 5,
    //     'margin_bottom' => 5,
    // ]);

    $mpdf->WriteHTML($html);
    return $mpdf->Output('order-' . $order->code . '.pdf', 'I');
}

}
