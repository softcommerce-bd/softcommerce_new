@php
    // Ensure variables exist and set safe defaults
    $supplierWisePurchased = $supplierWisePurchased ?? collect();
    $startDate =
        $startDate ??
        ($supplierWisePurchased->min('created_at')
            ? \Carbon\Carbon::parse($supplierWisePurchased->min('created_at'))->startOfDay()
            : \Carbon\Carbon::today()->startOfDay());
    $endDate =
        $endDate ??
        ($supplierWisePurchased->max('created_at')
            ? \Carbon\Carbon::parse($supplierWisePurchased->max('created_at'))->endOfDay()
            : \Carbon\Carbon::today()->endOfDay());

    $totalPaidAmount =
        $totalPaidAmount ??
        $supplierWisePurchased->sum(function ($purchase) {
            return ($purchase->total_advance_amount ?? 0) +
                ($purchase->total_cash_amount ?? 0) +
                ($purchase->total_cheque_amount ?? 0) +
                ($purchase->total_bank_transfer_amount ?? 0);
        });
    $totalAdvancedAmount = $totalAdvancedAmount ?? $supplierWisePurchased->sum('advanced_amount');
    $totalPurchaseAmount = $totalPurchaseAmount ?? $supplierWisePurchased->sum('net_total_amount');
    $advanceBalance = $advaceBalance ?? max($totalAdvancedAmount - $totalPurchaseAmount, 0);
    $paymentDue = $paymentDue ?? $totalPurchaseAmount - $totalPaidAmount;
    $balanceDue = $balanceDue ?? $totalPurchaseAmount - $totalAdvancedAmount;
@endphp

<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-outline-secondary btn-sm print_tab_pdf">
        <i class="fas fa-print"></i> Print
    </button>
    <button class="btn btn-outline-secondary btn-sm download_tab_pdf ms-2">
        <i class="fas fa-download"></i> Download PDF
    </button>
</div>

<div class="print-area">
    <div id="contact_ledger_div">
        <div class="col-md-12">
            <p><strong>{{ $supplier->contact_person_name }}</strong><br>
                {{ $supplier->address }}
            </p>
        </div>
        <div class="container mt-4">
            <div class="row">
                <!-- Recipient Information -->
                <div class="col-md-6">
                    <p class="blue-heading p-4 font-weight-bold">To:</p>
                    {{-- {{ $supplier }} --}}
                    <p>
                        <strong>{{ $supplier->business_name }}</strong><br>
                        {{ $supplier->address }}<br>
                        Phoenix, Arizona, USA<br>
                        Mobile: {{ $supplier->mobile }}<br>
                        Tax number: {{ $supplier->tax_number }}
                    </p>
                </div>

                <!-- Account Summary -->
                <div class="col-md-6">
                    <h3 class="mb-0 blue-heading p-4 font-weight-bold">Account Summary</h3>
                    {{-- {{ $startDate }} --}}
                    {{-- <small>{{ $startDate->format('m/d/Y') ?? '' }} To {{ $endDate->format('m/d/Y') ?? '' }}</small> --}}
                    <hr class="mt-1 mb-3">
                    <table class="table table-sm table-borderless" id="summary_table">
                        <tbody>
                            <tr>
                                <td>Total Purchase</td>
                                <td class="text-right">৳ {{ number_format($totalPurchaseAmount ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Total Paid</td>
                                <td class="text-right">৳ {{ number_format($totalPaidAmount ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Advance Balance</td>
                                <td class="text-right">৳ {{ $advanceBalance ?? 0, 2 }}</td>
                            </tr>
                            <tr>
                                <td><strong>Payment Due</strong></td>
                                <td class="text-right font-weight-bold">৳ {{ $paymentDue ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            {{-- <p class="text-center"><strong>Showing all invoices and payments between {{ $startDate->format('m/d/Y') }} and {{ $endDate->format('m/d/Y') }}</strong></p> --}}
            <div class="table-responsive">
                <table class="table table-bordered" id="invoices_table">
                    <thead>
                        <tr class="blue-heading">
                            <th class="text-center">Purchase Date</th>
                            <th class="text-center">Reference No</th>
                            <th class="text-center">Pay Term</th>
                            <th class="text-center">Pay Term Type</th>
                            <th class="text-center">Total Item</th>
                            <th class="text-center">Paid Amount</th>
                            <th class="text-center">Paid Date</th>
                            <th class="text-center">Location</th>
                            <th class="text-center">Total Payment Due</th>
                            <th class="text-center">Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($supplierWisePurchased as $purchase)
                            <tr>
                                <td>{{ $purchase->created_at->format('m/d/Y H:i') }}</td>
                                <td>{{ $purchase->reference_no }}</td>
                                <td class="text-right">{{ $purchase->pay_term }}</td>
                                <td class="text-right">{{ $purchase->pay_term_type }}</td>
                                <td class="text-right">{{ $purchase->total_item }}</td>
                                <td class="text-right">
                                    {{ ($purchase->total_advance_amount ?? 0) +
                                        ($purchase->total_cash_amount ?? 0) +
                                        ($purchase->total_cheque_amount ?? 0) +
                                        ($purchase->total_bank_transfer_amount ?? 0) }}
                                </td>
                                <td class="text-right">
                                    @php
                                        $dates = is_array($purchase->advanced_paid_on)
                                            ? $purchase->advanced_paid_on
                                            : json_decode($purchase->advanced_paid_on, true) ?? [];
                                    @endphp
                                    {{ collect($dates)->map(fn($d) => \Carbon\Carbon::parse($d)->format('m/d/Y'))->implode(', ') }}
                                </td>
                                <td class="text-right">{{ $purchase->business_location }}</td>
                                <td class="text-right">{{ $purchase->total_payment_due }}</td>
                                <td class="text-right">
                                    {{ is_array($purchase->payment_method)
                                        ? implode(', ', $purchase->payment_method)
                                        : (is_string($purchase->payment_method)
                                            ? implode(', ', json_decode($purchase->payment_method, true) ?? [])
                                            : $purchase->payment_method) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
    $(document).ready(function() {
        // Initialize ledger daterangepicker
        $('#ledger_date_range').daterangepicker({
            opens: 'left',
            locale: {
                format: 'MM/DD/YYYY'
            }
        });

        // Ledger fetch button
        $('#fetch_data').on('click', function() {
            var dateRange = $('#ledger_date_range').val();
            if (!dateRange) return; // exit if empty

            var [startDate, endDate] = dateRange.split(' - ');
            var supplierId = $('#supplier_id').val();

            $.ajax({
                url: "{{ route('ledger_data') }}", // your ledger route
                type: 'GET',
                data: {
                    start_date: startDate,
                    end_date: endDate,
                    supplier_id: supplierId
                },
                success: function(response) {
                    console.debug(response);
                    // Replace ledger tables container
                    $('#tables_container').html(response.tablesHtml);
                },
                error: function(xhr) {
                    console.error('Ledger AJAX error:', xhr.responseText);
                }
            });
        });
    });
</script>
