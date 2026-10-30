<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\Order;

class OrdersExport implements FromCollection, WithMapping, WithHeadings
{
    public function collection()
    {
        return Order::all();
    }

    public function headings(): array
    {
        return [
            'Order Code',
            'Num. of Products',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Seller',
            'Delivery Status',
            'Payment Type',
            'Payment Status',
            'SubTotal',
            'Tax',
            'Shipping Change',
            'Coupon',
            'Total'
        ];
    }

    /**
    * @var Order  $order
    */
    public function map($order): array
    {
        $subtotal = $order->orderDetails->sum('price');
        $tax = $order->orderDetails->sum('tax');
        $tax = $order->orderDetails->sum('tax');
        $shipping = optional($order->orderDetails->first())->shipping_cost; 

        $address = json_decode($order->shipping_address, true);

        return [
            $order->code,
            count($order->orderDetails),
            $address['name'],
            $address['email'],
            $address['phone'],
            $order->shop != null ? $order->shop->name : translate('Inhouse Order'),
            translate(ucfirst(str_replace('_', ' ', $order->delivery_status))),
            translate(ucfirst(str_replace('_', ' ', $order->payment_type))),
            translate(ucfirst($order->payment_status)),
            single_price($subtotal),
            single_price($tax),
            single_price($shipping),
            single_price($order->coupon_discount),
            single_price($order->grand_total),
        ];
    }
}


