@extends('backend.accounts.layout')
@section('accounts_content')
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="fw-600 mb-0"><i class="las la-truck text-primary mr-2"></i>{{ translate('Supplier Payments') }}</h5>
            <a href="{{ route('accounts.supplier-payments.create') }}" class="btn-account-color rounded-pill"><i
                    class="las la-plus"></i> {{ translate('Make Payment') }}</a>
        </div>

        <div class="card-body border-bottom">
            <div class="row">
                <div class="col-md-3 mb-3"><input type="text" id="filter_supplier" class="form-control"
                        placeholder="{{ translate('Supplier') }}"></div>
                <div class="col-md-2 mb-3"><input type="text" id="filter_payment_no" class="form-control"
                        placeholder="{{ translate('Payment No') }}"></div>
                <div class="col-md-2 mb-3">
                    <select id="filter_status" class="form-control">
                        <option value="">{{ translate('All') }}</option>
                        <option value="paid">{{ translate('Paid') }}</option>
                        <option value="partial">{{ translate('Partial') }}</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3"><input type="text" class="form-control aiz-date-range" id="filter_date_range"
                        placeholder="{{ translate('Date Range') }}"></div>
                <div class="col-md-2 mb-3"><input type="date" id="filter_to_date" class="form-control"></div>
                <div class="col-md-1 mb-3"><button class="btn-account-color btn-block" onclick="filterPayments()"><i
                            class="las la-search"></i></button></div>
            </div>
        </div>

        <div class="table-responsive d-none d-lg-block">
            <table class="table table-hover mb-0">
                <thead class="bg-account-color text-white">
                    <tr>
                        <th>#</th>
                        <th>{{ translate('Payment No') }}</th>
                        <th>{{ translate('Supplier') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Method') }}</th>
                        <th>{{ translate('Date') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th class="text-right">{{ translate('Actions') }}</th>
                    </tr>
                </thead>
                <tbody id="payments_table_body">@include('backend.accounts.supplier-payments.partials.table_rows')</tbody>
            </table>
        </div>

        <div class="d-block d-lg-none p-3" id="mobile_payments_container">@include('backend.accounts.supplier-payments.partials.mobile_cards')</div>

        <div class="card-footer bg-white d-flex justify-content-between">
            <div><strong>{{ translate('Total') }}: <span id="total_count">{{ $supplierPayments->total() }}</span></strong>
            </div>
            <div id="pagination_links">{{ $supplierPayments->links() }}</div>
        </div>
    </div>

    <div class="modal fade" id="viewPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body" id="view_payment_content"></div>
            </div>
        </div>
    </div>

    <script>
        function filterPayments() {
            $.ajax({
                url: "{{ route('accounts.supplier-payments.index') }}",
                data: {
                    supplier: $('#filter_supplier').val(),
                    payment_no: $('#filter_payment_no').val(),
                    status: $('#filter_status').val(),
                    date_range: $('#filter_date_range').val(),
                    to_date: $('#filter_to_date').val()
                },
                success: function(res) {
                    $('#payments_table_body').html(res.table_html);
                    $('#mobile_payments_container').html(res.mobile_html);
                    $('#total_count').text(res.total_count);
                    $('#pagination_links').html(res.pagination);
                }
            });
        }

        // function viewSupplierPayment(id) {
        //     $.get("{{ url('accounts/supplier-payments/show') }}/" + id, function(res) {
        //         if (res.success) {
        //             let p = res.data;
        //             let html =
        //                 `<div class="p-3"><p><strong>Payment No:</strong> ${p.payment_no}</p><p><strong>Supplier:</strong> ${p.supplier?.business_name}</p><p><strong>Amount:</strong> ${parseFloat(p.amount).toFixed(2)} ৳</p><p><strong>Method:</strong> ${p.payment_method}</p><p><strong>Status:</strong> <span class="badge badge-${p.status=='paid'?'success':'warning'}">${p.status}</span></p><p><strong>Date:</strong> ${p.payment_date}</p><p><strong>Notes:</strong> ${p.notes || '-'}</p></div>`;
        //             $('#view_payment_content').html(html);
        //             $('#viewPaymentModal').modal('show');
        //         }
        //     });
        // }

     function viewSupplierPayment(id) {
    let url = "{{ route('accounts.supplier-payments.show', ['id' => '__ID__']) }}".replace('__ID__', id);
    
    $.get(url, function(res) {
        if (res.success) {
            let p = res.data;
            
            // Format payment method
            let paymentMethodDisplay = p.payment_method;
            if (p.payment_method == 0 || p.payment_method == 'CASH') {
                paymentMethodDisplay = 'Cash';
            } else if (p.payment_method == 'BANK') {
                paymentMethodDisplay = 'Bank';
            } else if (p.payment_method == 'MBANKING') {
                paymentMethodDisplay = 'Mobile Banking';
            }
            
            // Get account name
            let accountName = res.account_name ?? '-';
            // alert(p.paymentAccount);
            // Get supplier info
            let supplierName = p.supplier ? p.supplier.business_name : '-';
            let supplierMobile = p.supplier ? p.supplier.mobile : '-';
            
            // Status badge
            let statusBadge = p.status == 'paid' 
                ? '<span class="badge badge-success badge-pill">Paid</span>' 
                : '<span class="badge badge-warning badge-pill">Partial</span>';
            
            // Parse attachments
            let attachmentsHtml = '';
            if (p.attachments && p.attachments !== '[]') {
                try {
                    let files = JSON.parse(p.attachments);
                    if (files.length > 0) {
                        attachmentsHtml = `
                            <div class="mt-2">
                                <strong>Attachments:</strong>
                                ${files.map(file => `
                                    <a href="/storage/${file}" target="_blank" class="d-block small">
                                        <i class="las la-paperclip"></i> ${file.split('/').pop()}
                                    </a>
                                `).join('')}
                            </div>`;
                    }
                } catch(e) {}
            }
            
            // Build purchases table
            let purchasesHtml = '';
            if (p.purchases && p.purchases.length > 0) {
                let totalPaid = 0;
                let rows = p.purchases.map(pur => {
                    let amount = parseFloat(pur.pivot.paid_amount || 0);
                    totalPaid += amount;
                    return `
                        <tr>
                            <td>${pur.reference_no}</td>
                            <td class="text-right">${amount.toFixed(2)} ৳</td>
                        </tr>`;
                }).join('');
                
                purchasesHtml = `
                    <div class="mt-3">
                        <h6 class="mb-2">Paid Invoices</h6>
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Invoice No</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows}
                                <tr class="font-weight-bold bg-light">
                                    <td>Total</td>
                                    <td class="text-right">${totalPaid.toFixed(2)} ৳</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>`;
            }
            
            let html = `
                <div class="p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="mb-0">${p.payment_no}</h5>
                        ${statusBadge}
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted pl-0" width="130">Supplier</td>
                                    <td class="pl-0">: ${supplierName}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Mobile</td>
                                    <td class="pl-0">: ${supplierMobile}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Amount</td>
                                    <td class="pl-0">: <strong>${parseFloat(p.amount).toFixed(2)} ৳</strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Due After Payment</td>
                                    <td class="pl-0">: ${parseFloat(p.due_amount).toFixed(2)} ৳</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Payment Method</td>
                                    <td class="pl-0">: ${paymentMethodDisplay}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Account</td>
                                    <td class="pl-0">: ${accountName}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-muted pl-0" width="130">Date</td>
                                    <td class="pl-0">: ${p.payment_date}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Transaction No</td>
                                    <td class="pl-0">: ${p.transaction_no || '-'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Paid From</td>
                                    <td class="pl-0">: ${p.paid_from || '-'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Reference No</td>
                                    <td class="pl-0">: ${p.reference_no || '-'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted pl-0">Notes</td>
                                    <td class="pl-0">: ${p.notes || '-'}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    ${attachmentsHtml}
                    ${purchasesHtml}
                    
                    <div class="text-right mt-3 pt-2 border-top">
                       
                        <button type="button" class="btn btn-sm btn-secondary ml-1" data-dismiss="modal">Close</button>
                    </div>
                </div>`;
                
            $('#view_payment_content').html(html);
            $('#viewPaymentModal').modal('show');
        } else {
            toastr.error(res.message || 'Error loading payment details');
        }
    }).fail(function(xhr) {
        console.error('Error:', xhr);
        toastr.error('Error loading payment details');
    });
}

        function deleteSupplierPayment(id) {
            if (confirm('{{ translate('Are you sure?') }}')) {
                // Use the named route with proper URL construction
                let url = "{{ route('accounts.supplier-payments.delete', ['id' => '__ID__']) }}".replace('__ID__', id);

                $.ajax({
                    url: url,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert(res.message || '{{ translate('Error deleting payment') }}');
                        }
                    },
                    error: function(xhr) {
                        alert(xhr.responseJSON?.message || '{{ translate('Error deleting payment') }}');
                    }
                });
            }
        }

        function deleteSupplierPayment(id) {
            if (confirm('{{ translate('Are you sure?') }}')) {
                $.ajax({
                    url: "{{ url('accounts/supplier-payments/delete') }}/" + id,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        if (res.success) location.reload();
                        else alert(res.message);
                    }
                });
            }
        }
    </script>
@endsection
