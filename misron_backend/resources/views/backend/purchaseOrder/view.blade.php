@extends('backend.layouts.app')
<style>
    .card {
        transition: all 0.2s;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .card:hover {
        box-shadow: 0 6px 10px rgba(0, 0, 0, 0.2);
        transform: translateY(-5px);
    }

    .card-body {
        overflow-x: hidden;
    }

    .table-borderless th,
    .table-borderless td {
        padding: 0.5rem;
        /* Adjust padding as needed */
        vertical-align: middle;
        /* Vertically align content to the middle */
    }
</style>
@section('content')
    {{-- @dd($purchase); --}}
    <form action="#" method="POST" enctype="multipart/form-data">
        @csrf
        <!-- Supplier Section -->
        <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">
        <input type="hidden" name="supplier_id" value="{{ $purchase->supplier_id }}">
        <div class="mt-4">
            <div class="card mb-4 rounded">
                <div class="card-body">
                    <div class="row">
                        <!-- Supplier -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="supplier_id">Supplier:*</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">
                                            <i class="fa fa-user"></i>
                                        </span>
                                    </div>
                                    <select class="form-control" id="supplier_id" name="supplier_id" disabled>
                                        <option selected>Please Select</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}"
                                                {{ $supplier->id == $purchase->supplier_id ? 'selected' : '' }}>
                                                {{ $supplier->business_name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <!-- Hidden field to submit the selected value -->
                                    <input type="hidden" name="supplier_id" value="{{ $purchase->supplier_id }}">
                                </div>
                            </div>
                        </div>

                        <!-- Reference No -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="ref_no">Reference No:</label>
                                <i class="fa fa-info-circle text-info" data-toggle="popover" data-trigger="hover"
                                    data-content="Leave empty to autogenerate"></i>
                                <input type="text" class="form-control" readonly id="ref_no" name="reference_no"
                                    value="{{ $purchase->reference_no ?? '' }}">
                            </div>
                            <div id="referInputMessage" class="message text-danger"></div>
                        </div>

                        <!-- Purchase Date -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="transaction_date">Purchase Date:*</label>
                                <input type="date" class="form-control" name="purchase_date"
                                    value="{{ $purchase->purchase_date ?? '' }}" readonly>
                            </div>
                        </div>

                        <!-- Business Location -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="location_id">Business Location:*</label>
                                <input type="text" class="form-control" name="business_location"
                                    value="{{ $purchase->business_location ?? '' }}" readonly>
                            </div>
                        </div>

                        <!-- Pay Term -->
                        <div class="col-md-3 mb-3">
                            <div class="form-group">
                                <label for="pay_term">Pay term:</label>
                                <i class="fa fa-info-circle text-info" data-toggle="popover" data-trigger="hover"
                                    data-content="Payments to be paid for purchases/sales within the given time period."></i>
                                <div class="input-group">
                                    <input type="number" class="form-control" placeholder="Pay term" id="pay_term"
                                        name="pay_term" min="0" required value="{{ $purchase->pay_term ?? '' }}"
                                        readonly>
                                    <select class="form-control" id="pay_term_type" name="pay_term_type" disabled>
                                        <option selected disabled value="">Please Select</option>
                                        <option value="months"
                                            {{ $purchase->pay_term_type == 'months' ? 'selected' : '' }}>
                                            Months</option>
                                        <option value="years" {{ $purchase->pay_term_type == 'years' ? 'selected' : '' }}>
                                            Years</option>
                                        <option value="days" {{ $purchase->pay_term_type == 'days' ? 'selected' : '' }}>
                                            Days</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Attach Document -->
                        @if (!empty($purchase->document))
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="document">Attach Document:</label>
                                    <div class="">
                                        <a href="{{ asset('storage/app/public/' . $purchase->document) }}" target="_blank"
                                            class="btn btn-link">
                                            View Current Document
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif
                        
                        <div class="col-md-3 mb-3">
                            <label for="pay_term">Status:</label>
                            <select class="form-control" id="status" name="status" disabled>
                                <option selected disabled value="">Select</option>
                                <option value="draft" {{ $purchase->status == 'draft' ? 'selected' : '' }}>
                                    Draft</option>
                                <option value="purchased" {{ $purchase->status == 'purchased' ? 'selected' : '' }}>
                                    Purchased</option>
                            </select>
                        </div>


                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Entry Section -->
        <div class="mb-4 bg-white shadow-sm rounded-xl">
            <div class="border border-gray-200 p-2 bg-white">
                <div class="overflow-x-auto">
                    <div class="py-2 align-middle sm:px-5">
                        <div class="table-responsive">
                            <!-- Table displaying purchase products -->
                            <table class="table table-condensed table-bordered text-center table-striped"
                                id="purchase_entry_table">
                                <thead>
                                    <tr>
                                        <th>Purchase Order ID</th>
                                        <th>Product ID</th>
                                        <th>Product Name</th>
                                        <th>Purchase Quantity</th>
                                        <th>Recieved Quantity</th>
                                        <th>Buying Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($purchaseProducts as $item)
                                        <tr>
                                            <td>{{ $item->purchase_order_id ?? 'N/A' }}</td>
                                            <td>{{ $item->product_id ?? 'N/A' }}</td>
                                            <td>{{ $item->product_name ?? 'N/A' }}</td>
                                            <td>{{ $item->purchase_quantity ?? 'N/A' }}</td>
                                            <td>{{ $item->recieved_quantity ?? 'N/A' }}</td>
                                            <td>{{ $item->buying_unit_cost ?? 'N/A' }}</td>
                                            <td>{{ $item->total_cost ?? 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        {{-- @dd($purchase); --}}
                        <!-- Summary section aligned to the right -->
                        <h5 class="text-center fw-700 mt-2">Payment History</h5>
                        <div class="">
                            <div class="row">
                                <div class="col-md-7">
                                    <table class="table border">
                                        <thead>
                                            <tr>
                                                <th>Purchase ID</th>
                                                <th>Advanced Paid On</th>
                                                <th>Payment Method</th>
                                                <th>Amount Paid</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                // Decode the payments JSON safely
                                                $payments = json_decode($purchase->advanced_paid_on, true) ?? [];
                                                $methods = json_decode($purchase->payment_method, true) ?? [];
                                            @endphp
                                            {{-- @dd($purchase); --}}
        
                                            @if (!empty($payments) && is_array($payments) && !empty($methods) && is_array($methods))
                                                @foreach ($payments as $index => $payment)
                                                {{-- @dd($payment); --}}
                                                    <tr>
                                                        <td>{{ $purchase->id }}</td>
                                                        {{-- <td>{{ $payment ?? 'N/A' }}</td> --}}
                                                        <td>{{ \Carbon\Carbon::parse($payment)->format('F j, Y') ?? 'N/A' }}</td>
                                                        <td>{{ ucfirst($methods[$index] ?? 'N/A') }}</td>
                                                        <td>
                                                            @if ($methods[$index] === 'advance')
                                                                ৳ {{ number_format($purchase->total_advance_amount, 2) }}
                                                            @elseif ($methods[$index] === 'cash')
                                                                ৳ {{ number_format($purchase->total_cash_amount, 2) }}
                                                            @elseif ($methods[$index] === 'cheque')
                                                                ৳ {{ number_format($purchase->total_cheque_amount, 2) }}
                                                            @elseif ($methods[$index] === 'bank_transfer')
                                                                ৳ {{ number_format($purchase->total_bank_transfer_amount, 2) }}
                                                            @else
                                                                ৳ 0.00
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td colspan="4">No payments found</td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-5">
                                    <table class="table border" >
                                        <tbody>
                                            <tr>
                                                <th>Total Items:</th>
                                                <td>{{ $purchase->total_item ?? '0' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Net Total Amount:</th>
                                                <td>৳ {{ number_format($purchase->net_total_amount, 2) ?? '0.00' }}</td>
                                            </tr>
                                            <tr>
                                                <th>Total Paid:</th>
                                                <td>৳
                                                    {{ number_format($purchase->net_total_amount - $purchase->total_payment_due, 2) ?? '0.00' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Total Due:</th>
                                                <td>৳ {{ number_format($purchase->total_payment_due, 2) ?? '0.00' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                           
                           
                        </div>



                    </div>
                </div>
            </div>
        </div>


        <!-- Payment Section -->

        @php
            $supplierCurrentBalance = \App\Models\Supplier::find($purchase->supplier_id);
        @endphp

        {{-- @dd($supplierCurrentBalance); --}}
        {{-- <div class="card mb-4 shadow-sm rounded">
            <div class="card-body">
                <h3 class="card-title">Add Payment</h3>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong class="ml-2">Advance Balance: {{ $supplierCurrentBalance->opening_balance }}</strong>
                        <input data-error-msg="Required advance balance not available" name="advance_balance"
                            type="hidden">
                    </div>
                </div>
                <div class="border border-gray-200 rounded p-2 p-sm-3">
                    <div id="payment-rows-container">
                        <div class="payment-row" id="payment-row-0">
                            <input type="hidden" class="payment_row_index" value="0">
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="amount_0">Amount:*</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-money-bill-alt"></i>
                                                </span>
                                            </div>
                                            <input class="form-control payment-amount" id="amount_0" required
                                                placeholder="Amount" name="payments[0][amount]" type="number"
                                                value="0.00">
                                        </div>
                                        <!-- Error message container -->
                                        <span id="amount-error" class="text-danger" style="display:none;">Required
                                            advance balance not available.</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="paid_on_0">Paid on:*</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fa fa-calendar"></i>
                                                </span>
                                            </div>
                                            <input class="form-control paid_on" required name="payments[0][paid_on]"
                                                type="date" value="">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="method_0">Payment Method:*</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">
                                                    <i class="fas fa-money-bill-alt"></i>
                                                </span>
                                            </div>
                                            <select class="form-control" required id="method_0"
                                                name="payments[0][method]">
                                                <option value="advance">Advance</option>
                                                <option value="cash">Cash</option>
                                                <option value="cheque">Cheque</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                            </select>


                                        </div>
                                    </div>
                                </div>

                                <!-- Action button: Initially 'Add More Payment', clones will show 'Delete' -->
                                <div class="col-md-1 d-flex align-items-center justify-content-center action-btn pt-2">
                                    <button type="button" class="btn btn-success" id="add-payment-btn"><i
                                            class="las la-plus"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Optional Notes -->
                    <div class="form-group">
                        <label for="note">Notes:</label>
                        <textarea class="form-control" id="note" name="payment_note" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-sm-12 text-right">
                            <strong>Payment due:</strong> <span id="payment_due">0.00</span>
                            <input type="hidden" id="payment_due_input" name="total_payment_due">
                        </div>
                    </div>

                    <br>

                    <!-- Submit Button -->
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" id="submit_button">Save Draft</button>
                    </div>
                </div>
            </div>
        </div> --}}

    </form>
@endsection

@section('script')
    <script>
        $(document).ready(function() {

            $('#add-payment-btn').on('click', function() {
                // Get the container where the payment rows are added
                let container = $('#payment-rows-container');

                // Get the current number of payment rows
                let currentIndex = $('.payment-row').length;

                // Clone the first payment row
                let newRow = $('.payment-row:first').clone();

                // Update the new row's inputs and attributes
                newRow.attr('id', `payment-row-${currentIndex}`);
                newRow.find('.payment-amount').attr('name', `payments[${currentIndex}][amount]`);
                newRow.find('.paid_on').attr('name', `payments[${currentIndex}][paid_on]`);
                newRow.find('select').attr('name', `payments[${currentIndex}][method]`);

                // Clear the values for the new row
                newRow.find('.payment-amount').val('0.00');
                newRow.find('.paid_on').val('');
                newRow.find('select').prop('selectedIndex', 0);

                // Remove any existing "Add More Payment" button from the cloned row and replace it with a "Delete" button
                newRow.find('#add-payment-btn').remove();
                newRow.find('.action-btn').html(
                    '<button type="button" class="btn btn-danger delete-payment-btn"><i class="las la-trash"></i></button>'
                ); // Add Delete button

                // Append the new row to the container
                container.append(newRow);
            });

            // Handle delete button click
            $(document).on('click', '.delete-payment-btn', function() {
                $(this).closest('.payment-row').remove();
            });

            // Bind the blur event to dynamically added rows using delegated event handling
            $(document).on('blur', '.payment-amount', function() {
                // Get the payment row's index
                let paymentRow = $(this).closest('.payment-row');
                let rowIndex = paymentRow.attr('id').split('-')[2];

                // Get the selected payment method for the specific row
                let paymentMethod = $(`#method_${rowIndex}`).val();

                // Get the payment amount and convert it to a float
                let amountPaymentValue = parseFloat($(`#amount_${rowIndex}`).val()) || 0;

                // Get the advance balance and convert it to a float
                let advanceBalance = parseFloat($('#advance_balance_text').text()) || 0;

                // Error message span
                let errorMessage = paymentRow.find('#amount-error');



                // Proceed with calculating the new payment due if no errors
                let paymentDueValue = parseFloat($('#payment_due').text()) || 0;
                let newPaymentDue = paymentDueValue - amountPaymentValue;

                // Update the payment due fields
                $('#payment_due').text(newPaymentDue.toFixed(2));
                $('#payment_due_input').val(newPaymentDue.toFixed(2));
            });
        });
    </script>
@endsection
