@extends('backend.layouts.app')

@section('content')

<div class="aiz-titlebar text-left mt-2 mb-3">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="h3 text-dark">{{translate('Draft Order Details')}}</h1>
            <p class="text-muted mt-1">{{translate('View complete draft order information')}}</p>
        </div>
        <div class="col-md-4 text-md-right">
            <a href="{{ route('draft.index') }}" class="btn btn-light">
                <i class="las la-arrow-left"></i> {{ translate('Back to List') }}
            </a>
            <a href="#" class="btn btn-danger confirm-delete" data-href="{{route('draft.delete', $draft->id)}}">
                <i class="las la-trash"></i> {{ translate('Delete Draft') }}
            </a>
        </div>
    </div>
</div>

<div class="row gutters-10">
    <div class="col-md-4">
        <!-- Customer Information Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">
                    <i class="las la-user-circle text-primary"></i> {{ translate('Customer Information') }}
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="avatar avatar-lg bg-soft-primary">
                        <span class="avatar-initials" style="font-size: 24px;">{{ substr($draft->name, 0, 2) }}</span>
                    </div>
                </div>
                
                <table class="table table-sm table-borderless">
                    <tr>
                        <td width="40%"><strong>{{ translate('Full Name') }}:</strong></td>
                        <td width="60%">{{ $draft->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>{{ translate('Phone') }}:</strong></td>
                        <td>{{ $draft->phone }}</td>
                    </tr>
                    @if($draft->email)
                    <tr>
                        <td><strong>{{ translate('Email') }}:</strong></td>
                        <td>{{ $draft->email }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td><strong>{{ translate('Address') }}:</strong></td>
                        <td>{{ $draft->address ?? translate('Not provided') }}</td>
                    </tr>
                    @if($draft->order_note)
                    <tr>
                        <td><strong>{{ translate('Order Note') }}:</strong></td>
                        <td>{{ $draft->order_note }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
        
        <!-- Order Information Card -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0 h6">
                    <i class="las la-info-circle text-primary"></i> {{ translate('Order Information') }}
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td width="45%"><strong>{{ translate('Created') }}:</strong></td>
                        <td width="55%">{{ date('d M Y, h:i A', strtotime($draft->created_at)) }}</td>
                    </tr>
                    <tr>
                        <td><strong>{{ translate('Last Updated') }}:</strong></td>
                        <td>{{ date('d M Y, h:i A', strtotime($draft->updated_at)) }}</td>
                    </tr>
                    <tr>
                        <td><strong>{{ translate('Delivery Type') }}:</strong></td>
                        <td>
                            <span class="badge badge-inline badge-info">
                                {{ ucfirst(str_replace('_', ' ', $draft->delivery_type ?? 'home_delivery')) }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Order Summary Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">
                    <i class="las la-shopping-cart text-primary"></i> {{ translate('Order Summary') }}
                </h5>
            </div>
            <div class="card-body p-0">
                @php
                    $productsData = json_decode($draft->products_id, true);
                    $allProducts = [];
                    $subTotal = 0;
                    
                    if(isset($productsData['data']) && is_array($productsData['data'])) {
                        foreach($productsData['data'] as $sellerIndex => $seller) {
                            if(isset($seller['cart_items']) && is_array($seller['cart_items'])) {
                                foreach($seller['cart_items'] as $item) {
                                    $price = preg_replace('/[^0-9.]/', '', $item['price'] ?? '0');
                                    $subTotal += floatval($price) * ($item['quantity'] ?? 1);
                                    $allProducts[] = (object)[
                                        'name' => $item['product_name'] ?? 'N/A',
                                        'price' => $item['price'] ?? '৳0',
                                        'quantity' => $item['quantity'] ?? 1,
                                        'variation' => $item['variation'] ?? null,
                                        'seller' => $seller['name'] ?? 'Inhouse'
                                    ];
                                }
                            }
                        }
                        $grandTotal = $productsData['grand_total'] ?? '৳' . number_format($subTotal, 2);
                    }
                @endphp
                
                <div class="table-responsive">
                    <table class="table aiz-table mb-0">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="40%">{{ translate('Product') }}</th>
                                <th width="15%">{{ translate('Unit Price') }}</th>
                                <th width="10%">{{ translate('Qty') }}</th>
                                <th width="15%">{{ translate('Total') }}</th>
                                <th width="15%">{{ translate('Seller') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allProducts as $key => $product)
                            <tr>
                                <td>{{ $key + 1 }}</td>
                                <td>
                                    <strong>{{ $product->name }}</strong>
                                    @if($product->variation)
                                        <br><small class="text-muted">{{ translate('Variation') }}: {{ $product->variation }}</small>
                                    @endif
                                </td>
                                <td>{{ $product->price }}</td>
                                <td>{{ $product->quantity }}</td>
                                <td class="text-primary font-weight-bold">
                                    @php
                                        $itemTotal = preg_replace('/[^0-9.]/', '', $product->price) * $product->quantity;
                                        echo '৳' . number_format($itemTotal, 2);
                                    @endphp
                                </td>
                                <td>{{ $product->seller }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="las la-box-open la-3x text-muted"></i>
                                    <p class="mt-2">{{ translate('No products found in this draft') }}</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                <!-- Order Totals -->
                <div class="border-top p-3 bg-light">
                    <div class="row">
                        <div class="col-md-6 offset-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-right"><strong>{{ translate('Subtotal') }}:</strong></td>
                                    <td class="text-right">৳{{ number_format($subTotal, 2) }}</td>
                                </tr>
                                <tr class="border-top">
                                    <td class="text-right"><strong class="h6">{{ translate('Grand Total') }}:</strong></td>
                                    <td class="text-right"><strong class="h5 text-primary">{{ $grandTotal }}</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="card mt-3">
            <div class="card-body">
                <div class="row gutters-5">
                    <div class="col-md-6">
                        {{--<a href="{{ route('draft.edit', $draft->id) }}" class="btn btn-primary btn-block">
                            <i class="las la-edit"></i> {{ translate('Edit Draft') }}
                        </a>--}}
                    </div>
                    <div class="col-md-6">
                       {{--<a href="{{ route('draft.convert-to-order', $draft->id) }}" class="btn btn-success btn-block">
                            <i class="las la-shopping-cart"></i> {{ translate('Convert to Order') }}
                        </a>--}}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('modal')
    @include('modals.delete_modal')
@endsection

@section('script')
<script type="text/javascript">
    $(document).ready(function() {
        // Initialize AIZ plugins if needed
        AIZ.plugins.bootstrapSelect('.aiz-selectpicker');
        AIZ.plugins.dataTable('.aiz-table');
    });
</script>
@endsection