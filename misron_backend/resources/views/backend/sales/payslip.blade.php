<html>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ translate('INVOICE') }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="UTF-8">

	<style media="all">
		@page {
			margin: 0;
			padding: 0;
		}
	
		body {
			font-size: 0.875rem;
			font-family: '<?php echo $font_family; ?>'; /* Replace with the appropriate font family */
			font-weight: normal;
			direction: <?php echo $direction; ?>; /* Replace with the appropriate direction */
			text-align: <?php echo $text_align; ?>; /* Replace with the appropriate text alignment */
			padding: 0;
			margin: 0;
		}

		.container {
            display: flex; /* Enables Flexbox */
            justify-content: space-between; /* Pushes elements to opposite ends */
            align-items: center; /* Aligns items vertically */
            padding: 5px;
            width: 80%;
        }


	
		.gry-color * {
			color: #000;
		}
	
		table {
			width: 100%; /* Ensure tables take the full width */
			border-collapse: collapse; /* Remove gaps between table cells */
		}
	
		table th {
			font-weight: normal;
		}
	
		table.padding th,
		table.padding td {
			padding: .25rem .7rem;
		}
	
		table.sm-padding td {
			padding: .1rem .7rem;
		}
	
		table.lg-padding td {
			padding: .4rem .7rem;
			padding-left: 5px;
		}
	
		.border-bottom td,
		.border-bottom th {
			border-bottom: 1px solid #eceff4;
		}
	
		.text-left {
			text-align: <?php echo $text_align; ?>; /* Replace with the appropriate text alignment */
		}
	
		.text-right {
			text-align: <?php echo $not_text_align; ?>; /* Replace with the appropriate text alignment */
		}
	
		.invoice-layout {
			width: 100%; /* Full width for the layout */
			font-family: Arial, sans-serif; /* Consistent font */
			padding: 20px; /* Add padding for visual clarity */
			box-sizing: border-box; /* Include padding in width calculation */
		}
	
		td {
			padding: 5px; /* Add padding to table cells */
			vertical-align: top; /* Align text to the top */
		}
	
		/* Additional styles specific to print layout */
		@media print {
			.invoice-layout {
				display: table; /* Use table layout for print */
				width: 100%; /* Full width */
			}
		}
	</style>
	
</head>

<body>
    <div>

        @php
            $logo = get_setting('system_logo_black');
        @endphp

        <div style="padding: 1rem;">
           <table>
                <tr>
                    <td>
                        @if ($logo != null)
                            <img src="{{ uploaded_asset($logo) }}" height="50" width="150" style="display:inline-block;">
                        @else
                            <img src="{{ static_asset('assets/img/logo.png') }}" height="50" width="150" style="display:inline-block;">
                        @endif
                    </td>
                </tr>
            </table>

        
			<table class="invoice-layout" width="100%">
				<tr>
					<!-- Left Column (Site Name, Address, Email, Phone) -->
					<td class="left" style="vertical-align: top; width: 33%;">
						<table>
							<tr>
								<td style="font-size: 1rem;" class="strong">{{ get_setting('site_name') }}</td>
							</tr>
							<tr>
								<td class="gry-color small">{{ get_setting('contact_address', null, App::getLocale()) }}</td>
							</tr>
							<tr>
								<td class="gry-color small">{{ translate('Phone') }}: {{ get_setting('contact_phone') }}</td>
							</tr>
						</table>
					</td>
			
			
					<!-- Right Column (Order Info: Order ID, Order Date, Payment Method) -->
					<td class="right" style="text-align: right; vertical-align: top; width: 33%;">
						<table>
							<tr>
								<td class="text-right small">
									<span class="gry-color small">{{ translate('Order ID') }}:</span>
									<span class="strong">{{ $order->code }}</span>
								</td>
							</tr>
							@if(!empty($order->pathao_id))
							<tr>
								<td class="text-right small">
									<span class="gry-color small">{{ translate('Pathao Tracking Code') }}:</span>
									<span class="strong">{{ $order->pathao_id ?? '' }}</span>
								</td>
							</tr>
							@endif
							<tr>
								<td class="text-right small">
									<span class="gry-color small">{{ translate('Order Date') }}:</span>
									<span class="strong">{{ date('d-m-Y', $order->date) }}</span>
								</td>
							</tr>

							<tr>
								<td class="text-right small">
									<span class="gry-color small">{{ translate('Payment Status') }}:</span>
									<span class="strong">{{ translate(ucfirst(str_replace('_', ' ', $order->payment_status))) }}</span>
								</td>
							</tr>

                            <tr>
								<td class="text-right small">
									<span class="gry-color small">{{ translate('Grand Total') }}:</span>
									<span>{{($order->update_grand_total??$order->grand_total)}} Taka</span>
								</td>
							</tr>
							
							@if( $order->steadfast_id)
								<tr>
									<td class="text-right small">
										<span class="gry-color small">{{ translate('SteadFast ID') }}:</span>
										<span class="strong">{{$order->steadfast_id}}</span>
									</td>
								</tr>
                            @endif

                            @if(!(is_null($order->update_grand_total)) && ($order->payment_status != 'unpaid'))
                                @if($order->update_grand_total > $order->grand_total )
                                    <tr>
                                        <td class="text-main text-bold">{{ translate('Due Amount:') }}</td>
                                        <td class="text-right">{{single_price ($order->update_grand_total - $order->grand_total) }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="text-main text-bold">{{ translate('Received Amount:') }}</td>
                                        <td class="text-right">{{ single_price($order->grand_total - $order->update_grand_total)}}</td>
                                    </tr>
                                @endif
                            @endif

						</table>
					</td>
				</tr>
			</table>
        </div>


		<div style="padding: 1rem;">
			<table class="invoice-layout" width="100%">
				<tr>
					<!-- Left Column (Site Name, Address, Email, Phone) -->
					<td class="left" style="vertical-align: top; width: 33%;">
						<table>
							@php
								$shipping_address = json_decode($order->shipping_address);
							@endphp
							<tr>
								<td class="strong small gry-color">{{ translate('Bill to') }}:</td>
							</tr>
							<tr>
								<td class="strong">{{ $shipping_address->name }}</td>
							</tr>
							<tr>
								<td class="gry-color small">{{ $shipping_address->address ?? '' }},
									{{ $shipping_address->city ?? '' }}, @if (isset(json_decode($order->shipping_address)->state))
										{{ json_decode($order->shipping_address)->state ?? '' }} -
									@endif {{ $shipping_address->postal_code ?? '' }},
									{{ $shipping_address->country ?? '' }}</td>
							</tr>
							<tr>
								<td class="gry-color small">{{ translate('Email') }}: {{ $shipping_address->email }}</td>
							</tr>
							<tr>
								<td class="gry-color small">{{ translate('Phone') }}: {{ $shipping_address->phone }}</td>
							</tr>
						</table>
					</td>
			
			
					<!-- Right Column (Order Info: Order ID, Order Date, Payment Method) -->
					<td class="right" style="text-align: right; vertical-align: top; width: 33%;">
						<div class="mb-3">
							@php
								$invoiceUrl = route('invoice.download', $order->id);
								$removedXML = '<?xml version="1.0" encoding="UTF-8"?>';
							@endphp
											
							{!! str_replace($removedXML, '', QrCode::size(100)->generate($invoiceUrl)) !!}
            			</div>
					</td>
				</tr>
			</table>
		</div>




		<div style="padding: 2rem;">
			<table class="padding text-left small border-bottom" style="width: 100%; border: 1px solid black; border-collapse: collapse;">
				<thead>
					<tr class="gry-color" style="background: #eceff4; text-align: center;">
						<th width="35%" style="border: 1px solid black; font-weight:700;">{{ translate('Product Name') }}</th>
						<th width="10%" style="border: 1px solid black; font-weight:700;">{{ translate('Qty') }}</th>
					</tr>
				</thead>
				<tbody class="strong">
					@foreach ($order->orderDetails as $key => $orderDetail)
						@if ($orderDetail->product != null)
							<tr style="text-align: left;">
								<td style="border: 1px solid black;">
									{{ $orderDetail->product->name }}
									@if ($orderDetail->variation != null)
										({{ $orderDetail->variation }})
									@endif
									<br>
									<small>
										@php
											$product_stock = json_decode($orderDetail->product->stocks->first(), true);
										@endphp
										{{ translate('SKU') }}: {{ $product_stock['sku'] }}
									</small>
								</td>

								<td style="border: 1px solid black;">{{ $orderDetail->quantity }}</td>
							</tr>
						@endif
					@endforeach
				</tbody>
			</table>
		</div>
    </div>
</body>

</html>
