<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Currency;
use App\Models\Language;
use App\Models\Order;
use PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class InvoiceController extends Controller
{
    public function invoice_download(Request $request, $id)
    {
        try {
            // Get authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            // Find order and verify ownership
            $order = Order::where('id', $id)->where('user_id', $user->id)->first();
            
            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }
            
            // Get currency and language from headers
            $currency_code = $request->header('Currency-Code', Currency::findOrFail(get_setting('system_default_currency'))->code);
            $language_code = $request->header('App-Language', 'en');
            
            // Get language settings
            $language = Language::where('code', $language_code)->first();
            
            if ($language && $language->rtl == 1) {
                $direction = 'rtl';
                $text_align = 'right';
                $not_text_align = 'left';
            } else {
                $direction = 'ltr';
                $text_align = 'left';
                $not_text_align = 'right';
            }
            
            // Set font family
            $font_family = $this->getFontFamily($currency_code, $language_code);
            
            // Generate PDF with options
            $pdf = PDF::loadView('backend.invoices.invoice', [
                'order' => $order,
                'font_family' => $font_family,
                'direction' => $direction,
                'text_align' => $text_align,
                'not_text_align' => $not_text_align
            ]);
            
            // Get PDF content directly without setting paper size
            $pdfContent = $pdf->output();
            
            // Return PDF with proper headers
            return Response::make($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="order-' . $order->code . '.pdf"',
                'Content-Length' => strlen($pdfContent),
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'Pragma' => 'public'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Invoice download error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to generate invoice',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getFontFamily($currency_code, $language_code)
    {
        if ($currency_code == 'BDT' || $language_code == 'bd') {
            return "'Hind Siliguri','sans-serif'";
        } elseif ($currency_code == 'KHR' || $language_code == 'kh') {
            return "'Hanuman','sans-serif'";
        } elseif ($currency_code == 'AMD') {
            return "'arnamu','sans-serif'";
        } elseif ($currency_code == 'AED' || $currency_code == 'EGP' || $language_code == 'sa' ||
                  $currency_code == 'IQD' || $language_code == 'ir' || $language_code == 'om' ||
                  $currency_code == 'ROM' || $currency_code == 'SDG' || $currency_code == 'ILS' ||
                  $language_code == 'jo') {
            return "'Baloo Bhaijaan 2','sans-serif'";
        } elseif ($currency_code == 'THB') {
            return "'Kanit','sans-serif'";
        } elseif ($currency_code == 'CNY' || $language_code == 'zh') {
            return "'yahei','sans-serif'";
        } elseif ($currency_code == 'kyat' || $language_code == 'mm') {
            return "'pyidaungsu','sans-serif'";
        } else {
            return "'Roboto','sans-serif'";
        }
    }
}