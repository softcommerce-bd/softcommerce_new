<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\User;
use App\Http\Resources\PosProductCollection;
use App\Models\Cart;
use App\Models\ChartOfAccount;
use App\Utility\FontUtility;
use App\Utility\PosUtility;
use Session;
use Mpdf\Mpdf;

class PosController extends Controller
{
    public function __construct()
    {
        // Staff Permission Check
        $this->middleware(['permission:pos_manager'])->only('admin_index');
        $this->middleware(['permission:pos_configuration'])->only('pos_activation');
    }

    public function index()
    {
        // dd('hello dear');
        $customers = User::where('user_type', 'customer')->orderBy('created_at', 'desc')->get();

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

        return view('backend.pos.index', compact('customers', 'cashAccounts', 'bankAccounts', 'mobileBankingAccounts'));
    }

    public function search(Request $request)
    {
        $products = PosUtility::product_search($request->only('category', 'brand', 'keyword'));

        $stocks = new PosProductCollection($products);
        $stocks->appends(['keyword' =>  $request->keyword, 'category' => $request->category, 'brand' => $request->brand]);
        return $stocks;
    }

    // Add product To cart
    public function addToCart(Request $request)
    {
        $stockId    = $request->stock_id;
        $userID     = Session::get('pos.user_id');
        $temUserId  = Session::get('pos.temp_user_id');
        if (!$temUserId && !$userID) {
            $temUserId = bin2hex(random_bytes(10));
            Session::put('pos.temp_user_id', $temUserId);
        }
        $response = PosUtility::addToCart($stockId, $userID, $temUserId);

        return array(
            'success' => $response['success'],
            'message' => $response['message'],
            'view' => view('backend.pos.cart')->render()
        );
    }

    //updated the quantity for a cart item
    public function updateQuantity(Request $request)
    {
        $cart = Cart::find($request->cartId);
        $response = PosUtility::updateCartItemQuantity($cart, $request->only(['cartId', 'quantity']));

        return array('success' => $response['success'], 'message' => $response['message'], 'view' => view('backend.pos.cart')->render());
    }

    //removes from Cart
    public function removeFromCart(Request $request)
    {
        Cart::where('id', $request->id)->delete();
        return view('backend.pos.cart');
    }

    //Shipping Address for admin
    public function getShippingAddress(Request $request)
    {
        Session::forget('pos.shipping_info');
        $user_id = $request->id;
        return ($user_id == '') ? view('backend.pos.guest_shipping_address') : view('backend.pos.shipping_address', compact('user_id'));
    }

    public function set_shipping_address(Request $request)
    {
        $data = PosUtility::get_shipping_address($request);

        $shipping_info = $data;
        $request->session()->put('pos.shipping_info', $shipping_info);
    }

    // Update user Cart data when user is changed
    public function updateSessionUserCartData(Request $request)
    {
        PosUtility::updateCartOnUserChange($request->only(['userId']));
        return view('backend.pos.cart');
    }

    //set Discount
    public function setDiscount(Request $request)
    {
        if ($request->discount >= 0) {
            Session::put('pos.discount', $request->discount);
        }
        return view('backend.pos.cart');
    }

    //set Shipping Cost
    public function setShipping(Request $request)
    {
        if ($request->shipping != null) {
            Session::put('pos.shipping', $request->shipping);
        }
        return view('backend.pos.cart');
    }

    //set partial payment
    public function partialPayment(Request $request)
    {
        // dd($request->all());
        if ($request->partial_payment >= 0) {
            Session::put('pos.partial_payment', $request->partial_payment);
        }
        return view('backend.pos.cart');
    }

    //order summary
    public function get_order_summary(Request $request)
    {
        return view('backend.pos.order_summary');
    }

    //order place
    // public function order_store(Request $request)
    // {
    //     $request->merge(['temp_usder_id' => Session::get('pos.temp_user_id'), 'shippingInfo' => Session::get('pos.shipping_info'), 'shippingCost' => Session::get('pos.shipping', 0), 'discount' => Session::get('pos.discount')]);
    //     $response = PosUtility::orderStore($request->except(['_token']));

    //     $shippingInfo = $request->shippingInfo;

    //     if ($shippingInfo) {
    //         $user = User::where('email', $shippingInfo['email'])
    //             ->orWhere('phone', $shippingInfo['phone'])
    //             ->first();

    //         if (!$user) {
    //             $user = User::create([
    //                 'name'        => $shippingInfo['name'],
    //                 'guest_email'       => $shippingInfo['email'],
    //                 'guest_phone'       => $shippingInfo['phone'],
    //                 'address'     => $shippingInfo['address'] ?? null,
    //                 'country'     => $shippingInfo['country'] ?? null,
    //                 'state'       => $shippingInfo['state'] ?? null,
    //                 'city'        => $shippingInfo['city'] ?? null,
    //                 'postal_code' => $shippingInfo['postal_code'] ?? null,
    //             ]);
    //         }
    //     }

    //     if ($response['success']) {
    //         Session::forget('pos.shipping_info');
    //         Session::forget('pos.shipping');
    //         Session::forget('pos.discount');
    //         Session::forget('pos.partial_payment');
    //         Session::forget('pos.user_id');
    //         Session::forget('pos.temp_user_id');
    //     }

    //     return $response;
    // }

    //order place
    public function order_store(Request $request)
    {
        // Log the incoming request data
        \Log::info('Order store request data:', $request->all());

        $request->merge([
            'temp_usder_id' => Session::get('pos.temp_user_id'),
            'shippingInfo' => Session::get('pos.shipping_info'),
            'shippingCost' => Session::get('pos.shipping', 0),
            'discount' => Session::get('pos.discount'),
            // Add payment data to the request
            'cash_account_id' => $request->cash_account_id,
            'cash_amount' => $request->cash_amount,
            'bank_account_id' => $request->bank_account_id,
            'bank_amount' => $request->bank_amount,
            'mobile_bank_account_id' => $request->mobile_bank_account_id,
            'mobile_amount' => $request->mobile_amount,
            'total_payment' => $request->total_payment,
            'grand_total' => $request->grand_total
        ]);

        $response = PosUtility::orderStore($request->except(['_token']));

        if ($response['success']) {
            Session::forget('pos.shipping_info');
            Session::forget('pos.shipping');
            Session::forget('pos.discount');
            Session::forget('pos.partial_payment');
            Session::forget('pos.user_id');
            Session::forget('pos.temp_user_id');
        }

        return $response;
    }

    public function configuration()
    {
        return view('backend.pos.pos_activation');
    }

    public function invoice($id)
    {
        $order = Order::findOrFail($id);

        $print_width = get_setting('print_width');
        if ($print_width == null) {
            flash(translate('Thermal printer size is not given in POS configuration'))->warning();
            return back();
        }

        $pdf_style_data = FontUtility::get_font_family();

        $html = view('backend.pos.thermal_invoice', compact('order', 'pdf_style_data', 'print_width'));

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => [$print_width, 1000]]);
        $mpdf->WriteHTML($html);
        // $mpdf->WriteHTML('<h1>Hello world!</h1>');
        $mpdf->page   = 0;
        $mpdf->state  = 0;
        unset($mpdf->pages[0]);
        // The $p needs to be passed by reference
        $p = 'P';
        // dd($mpdf->y);
        $mpdf->_setPageSize(array($print_width, $mpdf->y), $p);

        $mpdf->addPage();
        $mpdf->WriteHTML($html);

        $mpdf->Output('order-' . $order->code . '.pdf', 'I');
    }
}
