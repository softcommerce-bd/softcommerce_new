{{-- resources/views/backend/stock_adjustments/show_modal.blade.php --}}

<div class="table-responsive">
    <table class="table table-bordered">
        <tr>
            <th width="30%">{{ translate('Reference No') }}</th>
            <td>{{ $stock_adjustment->reference_no }}</td>
        </tr>
        <tr>
            <th>{{ translate('Date') }}</th>
            <td>{{ $stock_adjustment->transaction_date->format('m/d/Y H:i') }}</td>
        </tr>
        <tr>
            <th>{{ translate('Adjustment Type') }}</th>
            <td>
                <span class="badge badge-inline {{ $stock_adjustment->adjustment_type == 'normal' ? 'badge-info' : 'badge-warning' }}">
                    {{ ucfirst($stock_adjustment->adjustment_type) }}
                </span>
            </td>
        </tr>
        <tr>
            <th>{{ translate('Total Amount') }}</th>
            <td>{{ single_price($stock_adjustment->total_amount) }}</td>
        </tr>
        {{-- <tr>
            <th>{{ translate('Total Amount Recovered') }}</th>
            <td>{{ single_price($stock_adjustment->total_amount_recovered) }}</td>
        </tr> --}}
        <tr>
            <th>{{ translate('Reason') }}</th>
            <td>{{ $stock_adjustment->additional_notes ?: 'N/A' }}</td>
        </tr>
        <tr>
            <th>{{ translate('Added By') }}</th>
            <td>{{ $stock_adjustment->creator->name ?? 'N/A' }}</td>
        </tr>
    </table>

    <h6 class="mt-4 mb-3">{{ translate('Products') }}</h6>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>{{ translate('Product') }}</th>
                <th>{{ translate('Variant') }}</th>
                <th>{{ translate('Quantity') }}</th>
                <th>{{ translate('Unit Price') }}</th>
                <th>{{ translate('Subtotal') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stock_adjustment->lines as $line)
                <tr>
                    <td>
                        {{ $line->product->name ?? 'N/A' }}
                    </td>
                    <td>
                        @if($line->productStock && $line->productStock->variant)
                            <span class="badge badge-inline badge-info">{{ $line->productStock->variant }}</span>
                        @else
                            <span class="text-muted">--</span>
                        @endif
                    </td>
                    <td>{{ $line->quantity }}</td>
                    <td>{{ single_price($line->unit_price) }}</td>
                    <td>{{ single_price($line->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>