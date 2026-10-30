@props(['notifications'])

<div class="form-group">
    <div class="aiz-checkbox-inline ml-3">
        <label class="aiz-checkbox">
            <input type="checkbox" class="check-all">
            <span class="aiz-square-check"></span>{{ translate('Select All') }}
        </label>
    </div>
</div>

@php
    $notificationShowDesign = get_setting('notification_show_type');
    if ($notificationShowDesign != 'only_text') {
        $notifyImageDesign =
            $notificationShowDesign == 'design_2'
                ? 'rounded-1'
                : ($notificationShowDesign == 'design_3'
                    ? 'rounded-circle'
                    : '');
    }
@endphp

@forelse($notifications as $notification)
    <li class="list-group-item d-flex justify-content-between align-items- py-3">
        <div class="media text-inherit">
            <div class="media-body">
                @php
                    $user_type = auth()->user()->user_type;
                    $notificationType = get_notification_type($notification->notification_type_id, 'id');
                    // Always start with default text (string)
                    $notifyContent = $notificationType->default_text ?? '';
                @endphp

                <div class="d-flex">
                    <div class="form-group d-inline-block">
                        <label class="aiz-checkbox">
                            <input type="checkbox" class="check-one" name="id[]" value="{{ $notification->id }}">
                            <span class="aiz-square-check"></span>
                        </label>
                    </div>

                    @if ($notificationShowDesign != 'only_text')
                        <div class="size-35px mr-2">
                            <img src="{{ uploaded_asset($notificationType->image) }}"
                                onerror="this.onerror=null;this.src='{{ static_asset('assets/img/notification.png') }}';"
                                class="img-fit h-100 {{ $notifyImageDesign }}">
                        </div>
                    @endif

                    <div>
                        {{-- Order Related Notifications --}}
                        @if ($notification->type == 'App\Notifications\OrderNotification')
                            @php
                                $order_id = $notification->data['order_id'];
                                $order_code = $notification->data['order_code'];
                                $status = ucfirst($notification->data['status']);

                                // Route to order details (adjust route name as per your project)
                                $route = route('orders.show', encrypt($order_id));

                                // Build link
                                $orderLink = "<a href='" . $route . "'>Order #{$order_code}</a>";


                               // $route = route('orders.show', encrypt($order->id));
                               // $orderLink = "<a href='" . $route . "'>Order #{$order_code}</a>";

                                // Replace placeholders if using template, or directly assign message
                                $notifyContent = "New order placed: {$orderLink} (Status: {$status})";
                            @endphp

                            {{-- Shop Verification Related Notifications --}}
                        @elseif ($notification->type == 'App\Notifications\ShopVerificationNotification')
                            @php
                                if ($notification->data['status'] == 'submitted') {
                                    $route = route('sellers.show_verification_request', $notification->data['id']);
                                    $shopName = "<a href='" . $route . "'>" . $notification->data['name'] . '</a>';
                                    $notifyContent = str_replace('[[shop_name]]', $shopName, $notifyContent);
                                }
                            @endphp

                            {{-- Shop Product Related Notifications --}}
                        @elseif ($notification->type == 'App\Notifications\ShopProductNotification')
                            @php
                                $product_id = $notification->data['id'];
                                $product_type = $notification->data['type'];
                                $lang = env('DEFAULT_LANGUAGE');

                                $route =
                                    $user_type == 'admin'
                                        ? ($product_type == 'physical'
                                            ? route('products.seller.edit', ['id' => $product_id, 'lang' => $lang])
                                            : route('digitalproducts.edit', ['id' => $product_id, 'lang' => $lang]))
                                        : ($product_type == 'physical'
                                            ? route('seller.products.edit', ['id' => $product_id, 'lang' => $lang])
                                            : route('seller.digitalproducts.edit', [
                                                'id' => $product_id,
                                                'lang' => $lang,
                                            ]));

                                $productName = "<a href='" . $route . "'>" . $notification->data['name'] . '</a>';
                                $notifyContent = str_replace('[[product_name]]', $productName, $notifyContent);
                            @endphp

                            {{-- Seller Payout Notifications --}}
                        @elseif ($notification->type == 'App\Notifications\PayoutNotification')
                            @php
                                $amount = single_price($notification->data['payment_amount']);
                                $route =
                                    $user_type == 'admin'
                                        ? ($notification->data['status'] == 'pending'
                                            ? route('withdraw_requests_all')
                                            : route('sellers.payment_histories'))
                                        : ($notification->data['status'] == 'pending'
                                            ? route('seller.money_withdraw_requests.index')
                                            : route('seller.payments.index'));

                                $shopName = "<a href='" . $route . "'>" . $notification->data['name'] . '</a>';

                                $notifyContent = str_replace('[[shop_name]]', $shopName, $notifyContent);
                                $notifyContent = str_replace('[[amount]]', $amount, $notifyContent);
                            @endphp
                        @endif

                        <p class="mb-1 text-truncate-2">{!! $notifyContent !!}</p>
                        <small
                            class="text-muted">{{ date('F j Y, g:i a', strtotime($notification->created_at)) }}</small>
                    </div>
                </div>
            </div>
        </div>
    </li>
@empty
    <li class="list-group-item">
        <div class="py-4 text-center fs-16">
            {{ translate('No notification found') }}
        </div>
    </li>
@endforelse
