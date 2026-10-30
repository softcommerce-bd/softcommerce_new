<?php

namespace App\Http\Controllers\Api\V2;
use Illuminate\Support\Facades\Route;




Route::group(['prefix' => 'v2/auth', 'middleware' => ['app_language']], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('signup', [AuthController::class, 'signup']);
    Route::post('social-login', [AuthController::class, 'socialLogin']);
    Route::post('password/forget_request', [PasswordResetController::class, 'forgetRequest']);
    Route::post('password/confirm_reset', [PasswordResetController::class, 'confirmReset']);
    Route::post('password/resend_code', [PasswordResetController::class, 'resendCode']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('logout', [AuthController::class, 'logout']);
        Route::get('account-deletion', [AuthController::class, 'account_deletion']);
        Route::get('user', [AuthController::class, 'user']);
        Route::get('resend_code', [AuthController::class, 'resendCode']);
        Route::post('confirm_code', [AuthController::class, 'confirmCode']);
         // Dashboard routes - MOVED INSIDE THE AUTH MIDDLEWARE
        Route::get('dashboard/stats', [AuthController::class, 'index']);
    });

    Route::post('info', [AuthController::class, 'getUserInfoByAccessToken']);
    
      // Dashboard stats
    // Route::get('/dashboard/stats', [AuthController::class, 'getStats']);
 
    
    // Recent orders (optional, if you want to separate from purchases)
    // Route::get('/dashboard/recent-orders', [AuthController::class, 'getRecentOrders']);
});


Route::group(['prefix' => 'v2', 'middleware' => ['app_language']], function () {
   // SEO Routes
    Route::get('/seo-settings', [SEOController::class, 'getSettings']);
    Route::get('/seo/homepage', [SEOController::class, 'getHomepageSEO']);
    Route::get('/seo/product/{id}/{slug}', [SEOController::class, 'getProductSEO']);
    Route::get('/seo/category/{id}/{slug}', [SEOController::class, 'getCategorySEO']);
    Route::get('/seo/blog/{id}/{slug}', [SEOController::class, 'getBlogSEO']);
    Route::get('/seo/page/{slug}', [SEOController::class, 'getPageSEO']);
    Route::get('/seo/brand/{id}/{slug}', [SEOController::class, 'getBrandSEO']);
    Route::get('/seo/search', [SEOController::class, 'getSearchSEO']);
    // auction products routes
    Route::get('auction/products', [AuctionProductController::class, 'index']);
    Route::get('auction/bided-products', [AuctionProductController::class, 'bided_products_list'])->middleware('auth:sanctum');
    Route::get('auction/purchase-history', [AuctionProductController::class, 'user_purchase_history'])->middleware('auth:sanctum');
    Route::get('auction/products/{slug}', [AuctionProductController::class, 'details_auction_product']);
    Route::post('auction/place-bid', [AuctionProductBidController::class, 'store'])->middleware('auth:sanctum');

    Route::prefix('delivery-boy')->group(function () {
        Route::controller(DeliveryBoyController::class)->group(function () {
            Route::get('dashboard-summary/{id}', 'dashboard_summary')->middleware('auth:sanctum');
            Route::get('deliveries/completed/{id}', 'completed_delivery')->middleware('auth:sanctum');
            Route::get('deliveries/cancelled/{id}', 'cancelled_delivery')->middleware('auth:sanctum');
            Route::get('deliveries/on_the_way/{id}', 'on_the_way_delivery')->middleware('auth:sanctum');
            Route::get('deliveries/picked_up/{id}', 'picked_up_delivery')->middleware('auth:sanctum');
            Route::get('deliveries/assigned/{id}', 'assigned_delivery')->middleware('auth:sanctum');
            Route::get('collection-summary/{id}', 'collection_summary')->middleware('auth:sanctum');
            Route::get('earning-summary/{id}', 'earning_summary')->middleware('auth:sanctum');
            Route::get('collection/{id}', 'collection')->middleware('auth:sanctum');
            Route::get('earning/{id}', 'earning')->middleware('auth:sanctum');
            Route::get('cancel-request/{id}', 'cancel_request')->middleware('auth:sanctum');
            Route::post('change-delivery-status', 'change_delivery_status')->middleware('auth:sanctum');
            //Delivery Boy Order
            Route::get('purchase-history-details/{id}', 'App\Http\Controllers\Api\V2\DeliveryBoyController@details')->middleware('auth:sanctum');
            Route::get('purchase-history-items/{id}', 'App\Http\Controllers\Api\V2\DeliveryBoyController@items')->middleware('auth:sanctum');
        });
    });

    Route::apiResource('carts', CartController::class)->only('destroy');
    Route::controller(CartController::class)->group(function () {
        Route::post('cart-summary', 'summary');
        Route::post('cart-count', 'count');
        Route::post('carts/process', 'process');
        Route::post('carts/add', 'add');
        Route::post('carts/change-quantity', 'changeQuantity');
        Route::post('carts', 'getList');
        Route::post('guest-customer-info-check', 'guestCustomerInfoCheck');
        Route::post('updateCartStatus', 'updateCartStatus');

    });
    Route::post('guest-user-account-create', [AuthController::class, 'guestUserAccountCreate']);

    Route::post('checkout/send-otp', [CheckoutOtpController::class, 'sendOtp']);
    Route::post('checkout/verify-otp', [CheckoutOtpController::class, 'verifyOtp']);

    Route::post('coupon-apply', [CheckoutController::class, 'apply_coupon_code']);
    Route::post('coupon-remove', [CheckoutController::class, 'remove_coupon_code']);

    Route::post('delivery-info', [ShippingController::class, 'getDeliveryInfo']);
    Route::post('shipping_cost', [ShippingController::class, 'shipping_cost']);
    Route::post('carriers', [CarrierController::class, 'index']);

    Route::post('update-address-in-cart', [AddressController::class, 'updateAddressInCart']);

    Route::post('update-shipping-type-in-cart',  [AddressController::class, 'updateShippingTypeInCart']);
    Route::get('payment-types', [PaymentTypesController::class, 'getList']);
     Route::get('active-payment-methods', [PaymentTypesController::class, 'activePaymentMethod']);



    Route::group(['middleware' => ['app_user_unbanned']], function () {
        // customer downloadable product list
        Route::get('/digital/purchased-list', [PurchaseHistoryController::class, 'digital_purchased_list'])->middleware('auth:sanctum');
        Route::get('/purchased-products/download/{id}', [DigitalProductController::class, 'download'])->middleware('auth:sanctum');

        Route::get('wallet/history', [WalletController::class, 'walletRechargeHistory'])->middleware('auth:sanctum');

        Route::controller(ChatController::class)->group(function () {
            Route::get('chat/conversations', 'conversations')->middleware('auth:sanctum');
            Route::get('chat/messages/{id}', 'messages')->middleware('auth:sanctum');
            Route::post('chat/insert-message', 'insert_message')->middleware('auth:sanctum');
            Route::get('chat/get-new-messages/{conversation_id}/{last_message_id}', 'get_new_messages')->middleware('auth:sanctum');
            Route::post('chat/create-conversation', 'create_conversation')->middleware('auth:sanctum');
        });

        Route::controller(PurchaseHistoryController::class)->group(function () {
            Route::get('purchase-history', 'index')->middleware('auth:sanctum');
            Route::get('purchase-history-details/{id}', 'details')->middleware('auth:sanctum');
            Route::get('purchase-history-items/{id}', 'items')->middleware('auth:sanctum');
            Route::get('re-order/{id}', 're_order')->middleware('auth:sanctum');
        });

        Route::get('invoice/download/{id}', [InvoiceController::class, 'invoice_download'])->middleware('auth:sanctum');

        Route::prefix('classified')->group(function () {
            Route::controller(CustomerProductController::class)->group(function () {
                Route::get('/own-products', 'ownProducts')->middleware('auth:sanctum');
                Route::post('/store', 'store')->middleware('auth:sanctum');
                Route::post('/update/{id}', 'update')->middleware('auth:sanctum');
                Route::delete('/delete/{id}', 'delete')->middleware('auth:sanctum');
                Route::post('/change-status/{id}', 'changeStatus')->middleware('auth:sanctum');
            });
        });

        Route::get('customer/info', [CustomerController::class, 'show'])->middleware('auth:sanctum');

        Route::get('get-home-delivery-address', [AddressController::class, 'getShippingInCart'])->middleware('auth:sanctum');

        //Follow
        Route::controller(FollowSellerController::class)->group(function () {
            Route::get('/followed-seller', 'index')->middleware('auth:sanctum');
            Route::get('/followed-seller/store/{id}', 'store')->middleware('auth:sanctum');
            Route::get('/followed-seller/remove/{shopId}', 'remove')->middleware('auth:sanctum');
            Route::get('/followed-seller/check/{shopId}', 'checkFollow')->middleware('auth:sanctum');
        });

        Route::post('reviews/submit', [ReviewController::class, 'submit'])->name('api.reviews.submit')->middleware('auth:sanctum');

        Route::get('shop/user/{id}', [ShopController::class, 'shopOfUser'])->middleware('auth:sanctum');

        Route::apiResource('wishlists', WishlistController::class)->except(['index', 'update', 'show']);
        Route::controller(WishlistController::class)->group(function () {
            Route::get('wishlists-check-product', 'isProductInWishlist')->middleware('auth:sanctum');
            Route::get('wishlists-add-product', 'add')->middleware('auth:sanctum');
            Route::get('wishlists-remove-product', 'remove')->middleware('auth:sanctum');
            Route::get('wishlists', 'index')->middleware('auth:sanctum');
        });

        Route::controller(AddressController::class)->group(function () {
            Route::get('user/shipping/address', 'addresses')->middleware('auth:sanctum');
            Route::post('user/shipping/create', 'createShippingAddress')->middleware('auth:sanctum');
            Route::post('user/shipping/update', 'updateShippingAddress')->middleware('auth:sanctum');
            Route::post('user/shipping/update-location', 'updateShippingAddressLocation')->middleware('auth:sanctum');
            Route::post('user/shipping/make_default', 'makeShippingAddressDefault')->middleware('auth:sanctum');
            Route::get('user/shipping/delete/{address_id}', 'deleteShippingAddress')->middleware('auth:sanctum');    
        });
        
        Route::controller(ClubpointController::class)->group(function () {
            Route::get('clubpoint/get-list', 'get_list')->middleware('auth:sanctum');
            Route::post('clubpoint/convert-into-wallet', 'convert_into_wallet')->middleware('auth:sanctum');
        });
        
        Route::controller(RefundRequestController::class)->group(function () {
            Route::get('refund-request/get-list', 'get_list')->middleware('auth:sanctum');
            Route::post('refund-request/send', 'send')->middleware('auth:sanctum');
        });
        

        Route::get('bkash/begin', [BkashController::class, 'begin'])->middleware('auth:sanctum');
        Route::get('nagad/begin', [NagadController::class, 'begin'])->middleware('auth:sanctum');
        Route::post('payments/pay/wallet', [WalletController::class, 'processPayment'])->middleware('auth:sanctum');
        Route::post('payments/pay/cod', [PaymentController::class, 'cashOnDelivery'])->middleware('auth:sanctum');
        Route::post('payments/pay/manual', [PaymentController::class, 'manualPayment'])->middleware('auth:sanctum');
        Route::post('order/store', [OrderController::class, 'store'])->middleware('auth:sanctum');
        // Guest order route (no authentication required)
        Route::post('guest/order/store', [OrderController::class, 'store']);
        Route::get('order/guest-details/{id}', [OrderController::class, 'getOrderDetails']);
        Route::get('order/details/{id}', [OrderController::class, 'getOrderDetails'])->middleware('auth:sanctum');

        Route::get('order/cancel/{id}', [OrderController::class, 'order_cancel'])->middleware('auth:sanctum');
        
        

        // Get authenticated user's orders
        Route::get('/user/orders', [OrderController::class, 'userOrders'])->middleware('auth:sanctum');
        
        // Track order by code (public)
        Route::get('/track-order/{order_code}', [OrderController::class, 'trackOrder']);
        
        // Get single order details
        Route::get('/orders/{id}', [OrderController::class, 'show'])->middleware('auth:sanctum');
        
        // Cancel order
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->middleware('auth:sanctum');

        Route::controller(ProfileController::class)->group(function () {
            Route::get('profile/counters', 'counters')->middleware('auth:sanctum');
            Route::post('profile/update', 'update')->middleware('auth:sanctum');
            Route::post('profile/update-device-token', 'update_device_token')->middleware('auth:sanctum');
            Route::post('profile/update-image', 'updateImage')->middleware('auth:sanctum');
            Route::post('profile/image-upload', 'imageUpload')->middleware('auth:sanctum');
            Route::post('profile/check-phone-and-email', 'checkIfPhoneAndEmailAvailable')->middleware('auth:sanctum');
        });
        
        Route::controller(FileController::class)->group(function () {
            Route::post('file/image-upload', 'imageUpload')->middleware('auth:sanctum');
            Route::get('file-all', 'index')->middleware('auth:sanctum');
        });
        
        Route::post('file/upload', [SoftUploadController::class, 'upload'])->middleware('auth:sanctum');

        Route::get('wallet/balance', [WalletController::class, 'balance'])->middleware('auth:sanctum');
        Route::post('wallet/offline-recharge', [WalletController::class, 'offline_recharge'])->middleware('auth:sanctum');



        Route::controller(CustomerPackageController::class)->group(function () {
            Route::post('offline/packages-payment', 'purchase_package_offline')->middleware('auth:sanctum');
            Route::post('free/packages-payment', 'purchase_package_free')->middleware('auth:sanctum');
        });

        // Notification
        Route::controller(NotificationController::class)->group(function () {
            Route::get('all-notification', 'allNotification')->middleware('auth:sanctum');
            Route::get('unread-notifications', 'unreadNotifications')->middleware('auth:sanctum');
            Route::post('notifications/bulk-delete', 'bulkDelete')->middleware('auth:sanctum');
            Route::get('notifications/mark-as-read', 'notificationMarkAsRead')->middleware('auth:sanctum');
        });
        
        Route::get('products/last-viewed',[ProductController::class, 'lastViewedProducts'])->middleware('auth:sanctum');
    });

    //end user bann
    Route::controller(OnlinePaymentController::class)->group(function () {
        Route::get('online-pay/init', 'init')->middleware('auth:sanctum');
        Route::get('online-pay/success', 'paymentSuccess');
        Route::get('online-pay/done', 'paymentDone');
        Route::get('online-pay/failed', 'paymentFailed');
    });

    Route::get('coupon-list', [CouponController::class, 'couponList']);
    Route::get('coupon-products/{id}', [CouponController::class, 'getCouponProducts']);

    Route::get('get-search-suggestions', [SearchSuggestionController::class, 'getList']);
    Route::get('languages', [LanguageController::class, 'getList']);

    Route::controller(CustomerProductController::class)->group(function () {
        Route::get('classified/all', 'all');
        Route::get('classified/related-products/{slug}', 'relatedProducts');
        Route::get('classified/product-details/{slug}', 'productDetails');
    });
    
    Route::get('seller/top', [SellerController::class, 'topSellers']);

    Route::apiResource('banners', BannerController::class)->only('index');

    Route::apiResource('brands', BrandController::class)->only('index');
    Route::get('brands/top', [BrandController::class, 'top']);
    Route::get('selected-brands', [BrandController::class, 'selectedBrands']);

    Route::get('all-brands', [ProductController::class, 'getBrands'])->name('allBrands');

    Route::apiResource('business-settings', BusinessSettingController::class)->only('index');
    Route::get('all-custom-color', [BusinessSettingController::class, 'allColors']);

    Route::apiResource('categories', CategoryController::class)->only('index');
    Route::controller(CategoryController::class)->group(function () {
        Route::get('category/info/{slug}', 'info');
        Route::get('categories/featured', 'featured');
        Route::get('categories/home', 'home');
        Route::get('categories/top', 'top');
        Route::get('categories/home_categories_section', 'homeSetectedCategory');
        Route::get('categories/slider_banners/{slug}', 'categorySliderBanner');
        // Whole tree in one call, for the menu panel and the footer.
        Route::get('categories/tree', 'tree');
        Route::get('/top-featured-categories', 'topFeaturedCategories');
        Route::get('/selected-categories', 'selectedCategories');
    });
       // Draft order routes
    Route::controller(DraftApiController::class)->group(function () {
        Route::post('/draft/create-or-update', 'createOrUpdate');
        Route::get('/draft/get', 'getDraft');
        Route::delete('/draft/delete', 'deleteDraft');
    });
     // Subscribe
    Route::apiResource('subscribers', SubscriberControllerApi::class);
    
    Route::get('sub-categories/{id}', [SubCategoryController::class, 'index'])->name('subCategories.index');

    Route::apiResource('colors', ColorController::class)->only('index');

    Route::apiResource('currencies', CurrencyController::class)->only('index');

    Route::apiResource('customers', CustomerController::class)->only('show');

    Route::apiResource('general-settings', GeneralSettingController::class)->only('index');

    Route::apiResource('home-categories', HomeCategoryController::class)->only('index');

    Route::controller(FilterController::class)->group(function () {
        Route::get('filter/categories', 'categories');
        Route::get('filter/brands', 'brands');
    });
    

    // Route::apiResource('products', ProductController::class)->except(['store', 'update', 'destroy']);
    Route::apiResource('products', ProductController::class)->except(['store', 'update', 'destroy', 'show']);
    Route::controller(ProductController::class)->group(function () {
        Route::get('products/inhouse', 'inhouse');
        Route::get('buy-watch-products', 'buyWatchProducts');
        Route::get('products/seller/{id}', 'seller');
        Route::get('products/category/{slug}', 'categoryProducts')->name('api.products.category');
        // Route::get('category-wise-products/{slug}', 'categoryProducts')->name('api.products.category');
        Route::get('products/brand/{slug}', 'brand')->name('api.products.brand');
        Route::get('products/todays-deal', 'todaysDeal');
        Route::get('products/featured', 'featured');
        Route::get('products/top-picks', 'topPicks');
        Route::get('products/new-arrivals', 'newArrival');
        Route::get('products/best-selling', 'bestSelling');
        Route::get('products/trending-now', 'trendingNow');
        Route::get('products/best-seller', 'bestSeller');
        Route::get('products/top-from-seller/{slug}', 'topFromSeller');
        Route::get('products/frequently-bought/{slug}', 'frequentlyBought')->name('products.frequently_bought');

        Route::get('products/featured-from-seller/{id}', 'newFromSeller')->name('products.featuredromSeller');
        Route::get('products/search', 'search');
        Route::post('products/variant/price', 'getPrice');
        Route::get('products/digital', 'digital')->name('products.digital');
        // Route::get('products/{slug}/{user_id}', 'product_details');
        Route::get('products/{slug}/{user_id?}', 'product_details')->name('products.show');
    });
    Route::get('products/available-colors', [ProductController::class, 'getAvailableColors']);
    Route::get('available-colors', [ProductController::class, 'getset']);


    //Use this route outside of auth because initialy we created outside of auth we do not need auth initialy
    //We can't change it now because we didn't send token in header from mobile app.
    //We need the upload update Flutter app then we will write it in auth middleware.
    Route::controller(CustomerPackageController::class)->group(function () {
        Route::get("customer-packages", "customer_packages_list");
    });

    Route::get('reviews/product/{id}', [ReviewController::class, 'index'])->name('api.reviews.index');

    Route::apiResource('shops', ShopController::class)->only('index');
    Route::controller(ShopController::class)->group(function () {
        Route::get('shops/details/{id}', 'info')->name('shops.info');
        Route::get('shops/products/all/{id}', 'allProducts')->name('shops.allProducts');
        Route::get('shops/products/top/{id}', 'topSellingProducts')->name('shops.topSellingProducts');
        Route::get('shops/products/featured/{id}', 'featuredProducts')->name('shops.featuredProducts');
        Route::get('shops/products/new/{id}', 'newProducts')->name('shops.newProducts');
        Route::get('shops/brands/{id}', 'brands')->name('shops.brands');
    });
  
    Route::controller(SliderController::class)->group(function () {
        Route::get('sliders', 'sliders');
        Route::get('banners-one', 'bannerOne');
        Route::get('banners-two', 'bannerTwo');
        Route::get('banners-three', 'bannerThree');
    });
    //   todays deal top and bottom part api 

    Route::controller(SliderController::class)->group(function () {
        Route::get('todays-deal-all', 'getTodaysDeal');
        // Route::get('sliderss', 'sliders');
    });
  
    
    Route::controller(PolicyController::class)->group(function () {
        Route::get('policies/seller', 'sellerPolicy')->name('policies.seller');
        Route::get('policies/support', 'supportPolicy')->name('policies.support');
        Route::get('policies/return', 'returnPolicy')->name('policies.return');
    });
    
    Route::post('get-user-by-access_token', [UserController::class, 'getUserInfoByAccessToken']);

    Route::controller(AddressController::class)->group(function () {
        Route::get('cities', 'getCities');
        Route::get('states', 'getStates');
        Route::get('countries', 'getCountries');

        Route::get('cities-by-state/{state_id}', 'getCitiesByState');
        Route::get('states-by-country/{country_id}', 'getStatesByCountry');
    });

    Route::controller(StripeController::class)->group(function () {
        Route::any('stripe', 'stripe');
        Route::any('stripe/payment/callback', 'callback')->name('api.stripe.callback');
    });
    

    Route::any('paypal/payment/url', [PaypalController::class, 'getUrl'])->name('api.paypal.url');
    Route::any('amarpay', [AamarpayController::class, 'pay'])->name('api.amarpay.url');
    Route::any('khalti/payment/pay', [KhaltiController::class, 'pay'])->name('api.khalti.url');
    Route::any('razorpay/pay-with-razorpay', [RazorpayController::class, 'payWithRazorpay'])->name('api.razorpay.payment');
    Route::any('razorpay/payment', [RazorpayController::class, 'payment'])->name('api.razorpay.payment');
    Route::any('paystack/init', [PaystackController::class, 'init'])->name('api.paystack.init');
    Route::any('iyzico/init', [IyzicoController::class, 'init'])->name('api.iyzico.init');

    Route::controller(BkashController::class)->group(function () {
        Route::get('bkash/api/webpage/{token}/{amount}', 'webpage')->name('api.bkash.webpage');
        Route::any('bkash/api/execute/{token}', 'execute')->name('api.bkash.execute');
        Route::any('bkash/api/fail', 'fail')->name('api.bkash.fail');
        Route::post('bkash/api/process', 'process')->name('api.bkash.process');
    });
    
    Route::controller(NagadController::class)->group(function () {
        Route::any('nagad/verify/{payment_type}', 'verify')->name('app.nagad.callback_url');
        Route::post('nagad/process', 'process');
    });

    Route::get('sslcommerz/begin', [SslCommerzController::class, 'begin']);

    Route::controller(PaystationController::class)->group(function () {
        Route::post('paystation/begin', 'begin');
        // Hit by the gateway, not by the storefront, so neither is authenticated.
        Route::any('paystation/callback', 'callback');
        Route::post('paystation/ipn', 'ipn');
    });

    Route::any('flutterwave/payment/url', [FlutterwaveController::class, 'getUrl'])->name('api.flutterwave.url');
    Route::any('paytm/payment/pay', [PaytmController::class, 'pay'])->name('api.paytm.pay');
    Route::get('instamojo/pay', [InstamojoController::class, 'pay']);
    Route::get('payfast/initiate', [PayfastController::class, 'pay']);
    Route::get('/myfatoorah/initiate', [MyfatoorahController::class, 'pay']);
    Route::get('phonepe/payment/pay', [PhonepeController::class, 'pay']);
    Route::post('offline/payment/submit', [OfflinePaymentController::class, 'submit'])->name('api.offline.payment.submit');

    Route::controller(FlashDealController::class)->group(function (){
        Route::get('flash-deals', 'index');
        Route::get('flash-deals/info/{slug}', 'info');
        Route::get('flash-deal-products/{id}', 'products');
    });
    
    //Addon list
    Route::get('addon-list', [ConfigController::class, 'addon_list']);
    //Activated social login list
    Route::get('activated-social-login', [ConfigController::class, 'activated_social_login']);

    //Business Sttings list
    Route::post('business-settings', [ConfigController::class, 'business_settings']);
    
   Route::get('header-details', [ConfigController::class, 'headerDetails']);
    //Pickup Point list
    Route::get('pickup-list', [ShippingController::class, 'pickup_list']);


   
        Route::get('google-recaptcha', function () {
            return view("frontend.google_recaptcha.app_recaptcha");
        });

        Route::controller(PaypalController::class)->group(function (){
            Route::any('paypal/payment/done', 'getDone')->name('api.paypal.done');
            Route::any('paypal/payment/cancel', 'getCancel')->name('api.paypal.cancel');
        });
        Route::controller(AamarpayController::class)->group(function (){
            Route::any('amarpay/success', 'success')->name('api.amarpay.success');
            Route::any('amarpay/cancel', 'fail')->name('api.amarpay.cancel');
        });
        Route::controller(KhaltiController::class)->group(function (){
            Route::any('khalti/payment/success', 'paymentDone')->name('api.khalti.success');
            Route::any('khalti/payment/cancel', 'getCancel')->name('api.khalti.cancel');
        });
        
        Route::any('razorpay/success', [RazorpayController::class, 'payment_success'])->name('api.razorpay.success');
        Route::post('paystack/success', [PaystackController::class, 'payment_success'])->name('api.paystack.success');

        Route::controller(IyzicoController::class)->group(function (){
            Route::any('iyzico/callback', 'callback')->name('api.iyzico.callback');
            Route::post('iyzico/success', 'payment_success')->name('api.iyzico.success');
        });

        Route::controller(BkashController::class)->group(function (){
            Route::any('bkash/api/callback', 'callback')->name('api.bkash.callback');
            Route::post('bkash/api/success', 'payment_success')->name('api.bkash.success');
            Route::any('bkash/api/checkout/{token}/{amount}', 'checkout')->name('api.bkash.checkout');
        });
        Route::controller(StripeController::class)->group(function (){
            Route::any('stripe/create-checkout-session', 'create_checkout_session')->name('api.stripe.get_token');
            Route::get('stripe/success', 'payment_success');
            Route::any('stripe/cancel', 'cancel')->name('api.stripe.cancel');
        });
        Route::controller(SslCommerzController::class)->group(function (){
            Route::any('sslcommerz/success', 'payment_success');
            Route::any('sslcommerz/fail', 'payment_fail');
            Route::any('sslcommerz/cancel', 'payment_cancel');
        });
        
        Route::any('flutterwave/payment/callback', [FlutterwaveController::class, 'callback'])->name('api.flutterwave.callback');
        Route::any('paytm/payment/callback', [PaytmController::class, 'callback'])->name('api.paytm.callback');

        Route::controller(InstamojoController::class)->group(function (){
            Route::get('instamojo/success', 'success');
            Route::get('instamojo/failed', 'failed');
        });

        //Payfast routes <starts>
        Route::controller(PayfastController::class)->group(function () {
            Route::any('/payfast/notify', 'payfast_notify')->name('api.payfast.notify');
            Route::any('/payfast/return', 'payfast_return')->name('api.payfast.return');
            Route::any('/payfast/cancel', 'payfast_cancel')->name('api.payfast.cancel');
        });
        //Payfast routes <ends>

        Route::get('/myfatoorah/callback', [MyfatoorahController::class, 'callback'])->name('api.myfatoorah.callback');

        Route::controller(PhonepeController::class)->group(function () {
            Route::any('/phonepe/redirecturl', 'phonepe_redirecturl')->name('api.phonepe.redirecturl');
            Route::any('/phonepe/callbackUrl', 'phonepe_callbackUrl')->name('api.phonepe.callbackUrl');
        });
   

      // customer file upload
      Route::controller(CustomerFileUploadController::class)->middleware('auth:sanctum')->group(function () {
        Route::post('file/upload', 'upload');
        Route::get('file/all', 'index');
        Route::get('file/delete/{id}', 'destroy');
    });
    
    // Footer data routes 
    Route::get('/footer-data', [FooterController::class, 'index']);
    Route::get('/pages/{slug}', [PageController::class, 'show']);
    
//     Route::prefix('blogs')->group(function () {
//     // Get all blogs with filters
//     Route::get('/', [BlogApiController::class, 'index']);
    
//     // Search blogs
//     Route::get('/search', [BlogApiController::class, 'search']);
    
//     // Get recent blogs
//     Route::get('/recent', [BlogApiController::class, 'recent']);
    
//     // Get all categories
//     Route::get('/categories', [BlogApiController::class, 'categories']);
    
//     // Get blogs by category
//     Route::get('/category/{categorySlug}', [BlogApiController::class, 'byCategory']);
    
//     // Get single blog by slug (must be last)
//     Route::get('/{slug}', [BlogApiController::class, 'show']);
// });

Route::prefix('blogs')->group(function () {
    // Get all blogs with filters
    Route::get('/', [BlogApiController::class, 'index'])->name('blogs.index');
    
    // Search blogs
    Route::get('/search', [BlogApiController::class, 'search'])->name('blogs.search');
    
    // Get recent blogs
    Route::get('/recent', [BlogApiController::class, 'recent'])->name('blogs.recent');
    
    // Get all categories
    Route::get('/categories', [BlogApiController::class, 'categories'])->name('blogs.categories');
    
    // Get blogs by category
    Route::get('/category/{categorySlug}', [BlogApiController::class, 'byCategory'])->name('blogs.byCategory');
    
    // Get single blog by slug (must be last)
    Route::get('/{slug}', [BlogApiController::class, 'show'])->name('blogs.show');
});

// Test route to verify API is working
Route::get('/test', function() {
    return response()->json([
        'result' => true,
        'message' => 'API v2 is working'
    ]);
});

// IP Block Check - Public (used by Next.js frontend)
Route::get('/ip-check', [IpBlockCheckController::class, 'check']);

// In your routes file - inside the v2 prefix group:

// Public affiliate routes (no auth required)
Route::prefix('affiliate')->group(function () {
    Route::get('/info', [AffiliateController::class, 'affiliateInfo']);
    Route::post('/apply', [AffiliateController::class, 'applyForAffiliate']);
    Route::post('/track-click', [AffiliateController::class, 'trackClick']);
});

// Protected affiliate routes (auth required)
Route::group(['middleware' => ['auth:sanctum']], function() {
    Route::prefix('affiliate')->group(function () {
        Route::get('/dashboard', [AffiliateController::class, 'dashboard']);
        Route::get('/stats', [AffiliateController::class, 'affiliateStats']);
        Route::get('/logs', [AffiliateController::class, 'affiliateLogs']);
        Route::get('/payment-history', [AffiliateController::class, 'paymentHistory']);
        Route::get('/withdraw-history', [AffiliateController::class, 'withdrawHistory']);
        Route::get('/payment-settings', [AffiliateController::class, 'paymentSettings']);
        Route::post('/payment-settings', [AffiliateController::class, 'storePaymentSettings']);
        Route::post('/withdraw', [AffiliateController::class, 'storeWithdrawRequest']);
        Route::get('/referral-stats', [AffiliateController::class, 'referralStats']);
    });
});

});


// Sales funnel editor API, used by the visual editor in the Next.js app.
// "session" trades a one-time handoff code from the admin panel for an editor
// token; everything else needs that token (see FunnelEditorAccess).
Route::prefix('v2/funnel-editor')->group(function () {
    Route::post('session', [FunnelEditorController::class, 'exchange'])->middleware('throttle:funnel-editor-session');

    Route::middleware(['auth:sanctum', 'funnel.editor'])->group(function () {
        Route::get('session', [FunnelEditorController::class, 'me']);
        Route::delete('session', [FunnelEditorController::class, 'endSession']);
        Route::get('registry', [FunnelEditorController::class, 'registry']);

        Route::get('funnels', [FunnelEditorController::class, 'index']);
        Route::post('funnels', [FunnelEditorController::class, 'store']);
        Route::get('funnels/{id}', [FunnelEditorController::class, 'show']);
        Route::put('funnels/{id}', [FunnelEditorController::class, 'updateDetails']);
        Route::delete('funnels/{id}', [FunnelEditorController::class, 'destroy']);
        Route::put('funnels/{id}/draft', [FunnelEditorController::class, 'saveDraft'])->middleware('throttle:120,1');
        Route::post('funnels/{id}/validate', [FunnelEditorController::class, 'validateDraft']);
        Route::post('funnels/{id}/publish', [FunnelEditorController::class, 'publish']);
        Route::post('funnels/{id}/unpublish', [FunnelEditorController::class, 'unpublish']);
        Route::post('funnels/{id}/archive', [FunnelEditorController::class, 'archive']);
        Route::post('funnels/{id}/restore', [FunnelEditorController::class, 'restore']);
        Route::post('funnels/{id}/duplicate', [FunnelEditorController::class, 'duplicate']);
        Route::get('funnels/{id}/versions', [FunnelEditorController::class, 'versions']);
        Route::post('funnels/{id}/versions/{versionId}/rollback', [FunnelEditorController::class, 'rollback']);
        Route::get('funnels/{id}/versions/{versionId}', [FunnelEditorController::class, 'version']);
        Route::post('funnels/{id}/schedule', [FunnelEditorController::class, 'schedule']);
        Route::post('funnels/{id}/schedule/cancel', [FunnelEditorController::class, 'cancelSchedule']);
        Route::put('funnels/{id}/end-time', [FunnelEditorController::class, 'endTime']);
        Route::get('funnels/{id}/preview-links', [FunnelEditorController::class, 'previewLinks']);
        Route::post('funnels/{id}/preview-links', [FunnelEditorController::class, 'createPreviewLink'])->middleware('throttle:30,1');
        Route::delete('funnels/{id}/preview-links/{linkId}', [FunnelEditorController::class, 'revokePreviewLink']);
        Route::post('funnels/{id}/template-slots', [FunnelEditorController::class, 'fillTemplateSlot']);
        Route::get('templates', [FunnelEditorController::class, 'templates']);
        Route::post('funnels/{id}/test-order-link', [FunnelEditorController::class, 'testOrderLink'])->middleware('throttle:30,1');
        Route::get('saved-sections', [FunnelEditorController::class, 'savedSections']);
        Route::post('saved-sections', [FunnelEditorController::class, 'createSavedSection'])->middleware('throttle:60,1');
        Route::put('saved-sections/{sectionId}', [FunnelEditorController::class, 'updateSavedSection']);
        Route::post('saved-sections/{sectionId}/duplicate', [FunnelEditorController::class, 'duplicateSavedSection'])->middleware('throttle:60,1');
        Route::delete('saved-sections/{sectionId}', [FunnelEditorController::class, 'deleteSavedSection']);

        Route::get('media', [FunnelEditorController::class, 'media']);
        Route::post('media', [FunnelEditorController::class, 'uploadMedia'])->middleware('throttle:30,1');
        Route::get('products', [FunnelEditorController::class, 'products']);
        Route::post('offers/resolve', [FunnelEditorController::class, 'resolveOffers'])->middleware('throttle:120,1');
        Route::post('offers/quote', [FunnelEditorController::class, 'quoteOffers'])->middleware('throttle:240,1');
    });
});

// Customer-facing funnel data: live offers and the authoritative price of a
// selection, for the published version only.
Route::prefix('v2/funnels')->group(function () {
    Route::get('/', [FunnelPublicController::class, 'index'])->middleware('throttle:funnel-page');
    Route::get('{slug}', [FunnelPublicController::class, 'show'])->middleware('throttle:funnel-page');
    Route::get('{slug}/offers', [FunnelPublicController::class, 'offers'])->middleware('throttle:funnel-offers');
    Route::post('{slug}/quote', [FunnelPublicController::class, 'quote'])->middleware('throttle:funnel-quote');
    Route::post('{slug}/events', [FunnelPublicController::class, 'events'])->middleware('throttle:funnel-events');

    // Draft behind a secret preview link. Reads only; ordering is never possible from a preview.
    Route::get('{slug}/preview/{token}', [FunnelPublicController::class, 'preview'])->middleware('throttle:funnel-preview');
    Route::get('{slug}/preview/{token}/offers', [FunnelPublicController::class, 'previewOffers'])->middleware('throttle:funnel-preview');
    Route::post('{slug}/preview/{token}/quote', [FunnelPublicController::class, 'previewQuote'])->middleware('throttle:funnel-preview');

    // Ordering from a published funnel. Refused with a clear reason until FUNNEL_CHECKOUT_ENABLED is on.
    Route::get('{slug}/checkout/config', [FunnelCheckoutController::class, 'config'])->middleware('throttle:funnel-config');
    Route::post('{slug}/checkout', [FunnelCheckoutController::class, 'place'])->middleware('throttle:funnel-checkout');
    Route::get('{slug}/orders/{receipt}', [FunnelCheckoutController::class, 'order'])->middleware('throttle:funnel-order');
    Route::post('{slug}/orders/{receipt}/pay', [FunnelCheckoutController::class, 'pay'])->middleware('throttle:funnel-pay');
});
