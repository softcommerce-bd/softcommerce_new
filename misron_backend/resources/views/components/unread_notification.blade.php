@props(['notifications'])

@php
    $notificationShowDesign = get_setting('notification_show_type');
    $notifyImageDesign = '';
    if ($notificationShowDesign != 'only_text') {
        if ($notificationShowDesign == 'design_2') {
            $notifyImageDesign = 'rounded-1';
        } elseif ($notificationShowDesign == 'design_3') {
            $notifyImageDesign = 'rounded-circle';
        }
    }
    $user_type = auth()->user()->user_type;
@endphp

@forelse($notifications as $notification)
    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
        <div class="media text-inherit">
            <div class="media-body">

                @php
                    $notificationType = get_notification_type($notification->notification_type_id, 'id');

                    // Default content
                    $notifyContent = $notificationType && $notificationType->getTranslation('default_text')
                        ? $notificationType->getTranslation('default_text')
                        : '';

                    // ORDER NOTIFICATION
                    if ($notification->type == 'App\Notifications\OrderNotification') {
                        $orderCode = $notification->data['order_code'];
                        $status = ucfirst($notification->data['status']);
                        $route = $user_type == 'admin'
                            ? route('all_orders.show', encrypt($orderCode))
                            : route('seller.orders.show', encrypt($orderCode));
                        $orderCodeLink = "<a href='{$route}' class='text-blue'>{$orderCode}</a>";

                        $notifyContent = $notifyContent
                            ? str_replace('[[order_code]]', $orderCodeLink, $notifyContent)
                                ->str_replace('[[status]]', $status, $notifyContent)
                            : "Order {$orderCodeLink} has been {$status}.";

                    // SHOP VERIFICATION NOTIFICATION
                    } elseif ($notification->type == 'App\Notifications\ShopVerificationNotification') {
                        $shopName = $notification->data['name'] ?? 'Shop';
                        if ($notification->data['status'] == 'submitted') {
                            $route = route('sellers.show_verification_request', $notification->data['id']);
                            $shopLink = "<a href='{$route}' class='text-blue'>{$shopName}</a>";
                            $notifyContent = $notifyContent
                                ? str_replace('[[shop_name]]', $shopLink, $notifyContent)
                                : "Shop verification submitted for {$shopLink}.";
                        }

                    // SHOP PRODUCT NOTIFICATION
                    } elseif ($notification->type == 'App\Notifications\ShopProductNotification') {
                        $productName = $notification->data['name'] ?? 'Product';
                        $product_id = $notification->data['id'];
                        $product_type = $notification->data['type'] ?? 'physical';
                        $lang = env('DEFAULT_LANGUAGE');

                        $route = $user_type == 'admin'
                            ? ($product_type == 'physical'
                                ? route('products.seller.edit', ['id'=>$product_id, 'lang'=>$lang])
                                : route('digitalproducts.edit', ['id'=>$product_id, 'lang'=>$lang]))
                            : ($product_type == 'physical'
                                ? route('seller.products.edit', ['id'=>$product_id, 'lang'=>$lang])
                                : route('seller.digitalproducts.edit', ['id'=>$product_id, 'lang'=>$lang]));

                        $productLink = "<a href='{$route}' class='text-blue'>{$productName}</a>";
                        $notifyContent = $notifyContent
                            ? str_replace('[[product_name]]', $productLink, $notifyContent)
                            : "Product {$productLink} has a new update.";

                    // PAYOUT NOTIFICATION
                    } elseif ($notification->type == 'App\Notifications\PayoutNotification') {
                        $amount = single_price($notification->data['payment_amount'] ?? 0);
                        $shopName = $notification->data['name'] ?? 'Shop';
                        $route = $user_type == 'admin'
                            ? ($notification->data['status'] == 'pending' ? route('withdraw_requests_all') : route('sellers.payment_histories'))
                            : ($notification->data['status'] == 'pending' ? route('seller.money_withdraw_requests.index') : route('seller.payments.index'));
                        $shopLink = "<a href='{$route}' class='text-blue'>{$shopName}</a>";

                        $notifyContent = $notifyContent
                            ? str_replace('[[shop_name]]', $shopLink, $notifyContent)
                                ->str_replace('[[amount]]', $amount, $notifyContent)
                            : "Payout of {$amount} for {$shopLink}.";
                    }
                @endphp

                <div class="d-flex">
                    @if ($notificationShowDesign != 'only_text')
                        <div class="size-35px mr-2">
                            <img src="{{ $notificationType && $notificationType->image ? uploaded_asset($notificationType->image) : static_asset('assets/img/notification.png') }}"
                                 class="img-fit h-100 {{ $notifyImageDesign }}"
                                 onerror="this.onerror=null;this.src='{{ static_asset('assets/img/notification.png') }}';">
                        </div>
                    @endif

                    <div>
                        <a href="{{ $user_type == 'admin' || $user_type == 'staff'
                            ? route('admin.notification.read-and-redirect', encrypt($notification->id))
                            : route('seller.notification.read-and-redirect', encrypt($notification->id)) }}">
                            <p class="mb-1 text-dark text-truncate-2">{!! $notifyContent !!}</p>
                            <small class="text-muted">{{ $notification->created_at->format('F j Y, g:i a') }}</small>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </li>
@empty
    <li class="list-group-item">
        <div class="py-4 text-center fs-16">{{ translate('No notification found') }}</div>
    </li>
@endforelse
