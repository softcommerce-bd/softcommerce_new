@extends('backend.accounts.layout')
@section('accounts_content')
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h5 class="fw-600 mb-0"><i class="las la-truck text-primary mr-2"></i>
                {{ isset($payment) ? translate('Edit Supplier Payment') : translate('Make Supplier Payment') }}</h5>
        </div>
        <div class="card-body">
            <form id="supplierPaymentForm" method="POST"
                action="{{ isset($payment) ? route('accounts.supplier-payments.update', $payment->id) : route('accounts.supplier-payments.store') }}"
                enctype="multipart/form-data">
                @csrf @if (isset($payment))
                    @method('PUT')
                @endif

                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group row">
                            <label class="col-form-label col-5 font-weight-medium">{{ translate('Date') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-7"><input type="date" name="payment_date" id="payment_date"
                                    class="form-control"
                                    value="{{ old('payment_date', isset($payment) ? $payment->payment_date : date('Y-m-d')) }}"
                                    required></div>
                        </div>
                    </div>
                    <div class="col-md-6 ml-auto">
                        <div class="form-group">
                            <label class="font-weight-medium">{{ translate('Supplier') }} <span
                                    class="text-danger">*</span></label>
                            <select name="supplier_id" id="supplier_id" class="form-control select2-single"
                                style="width:100%" required onchange="loadSupplierDue()">
                                <option value="">{{ translate('Search Supplier') }}</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" data-due="{{ $supplier->due_amount }}"
                                        {{ old('supplier_id', isset($payment) ? $payment->supplier_id : '') == $supplier->id ? 'selected' : '' }}>
                                        {{ $supplier->business_name }}
                                        {{ $supplier->mobile ? '- ' . $supplier->mobile : '' }}</option>
                                @endforeach
                            </select>
                            <div id="supplier_due_display" class="text-muted mt-1"></div>
                            <input type="hidden" name="supplier_due_amount" id="supplier_due_amount">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5">
                        <div class="form-group row">
                            <label class="col-form-label col-5 font-weight-medium">{{ translate('Payment Method') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-7">
                                <select name="payment_method" id="payment_method" class="form-control" required
                                    onchange="togglePaymentMethod()">
                                    <option value="">{{ translate('Select') }}</option>
                                    <option value="CASH"
                                        {{ old('payment_method', isset($payment) ? $payment->payment_method : '') == 'CASH' ? 'selected' : '' }}>
                                        {{ translate('Cash') }}</option>
                                    <option value="BANK"
                                        {{ old('payment_method', isset($payment) ? $payment->payment_method : '') == 'BANK' ? 'selected' : '' }}>
                                        {{ translate('Bank') }}</option>
                                    <option value="MBANKING"
                                        {{ old('payment_method', isset($payment) ? $payment->payment_method : '') == 'MBANKING' ? 'selected' : '' }}>
                                        {{ translate('Mobile Banking') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group row" id="account_row">
                            <label class="col-form-label col-5 font-weight-medium">{{ translate('Account') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-7">
                                <select name="account_head_id" id="account_head_id" class="form-control" required>
                                    <option value="">{{ translate('Select Account') }}</option>
                                </select>
                                <small class="text-danger" id="balance_error"
                                    style="display:none;">{{ translate('Insufficient balance') }}</small>
                            </div>
                        </div>

                        <div id="mobile_banking_fields" style="display:none;">
                            <div class="form-group row">
                                <label class="col-form-label col-5 font-weight-medium">{{ translate('Transaction No') }}
                                    <span class="text-danger">*</span></label>
                                <div class="col-7"><input type="text" name="transaction_no" id="transaction_no"
                                        class="form-control"
                                        value="{{ old('transaction_no', isset($payment) ? $payment->transaction_no : '') }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-form-label col-5 font-weight-medium">{{ translate('Paid From') }}</label>
                                <div class="col-7"><input type="text" name="paid_from" id="paid_from"
                                        class="form-control"
                                        value="{{ old('paid_from', isset($payment) ? $payment->paid_from : '') }}"></div>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-form-label col-5 font-weight-medium">{{ translate('Paid Amount') }} <span
                                    class="text-danger">*</span></label>
                            <div class="col-7"><input type="number" step="0.01" name="amount" id="amount"
                                    class="form-control text-right"
                                    value="{{ old('amount', isset($payment) ? $payment->amount : '') }}" placeholder="0.00"
                                    required oninput="validateAmount()"></div>
                        </div>

                        <div class="form-group row">
                            <label class="col-form-label col-4 font-weight-medium">{{ translate('Note') }}</label>
                            <div class="col-8">
                                <textarea name="notes" id="notes" rows="3" class="form-control">{{ old('notes', isset($payment) ? $payment->notes : '') }}</textarea>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-form-label col-4 font-weight-medium">{{ translate('Attachment') }}</label>
                            <div class="col-8">
                                <div class="custom-file"><input type="file" class="custom-file-input"
                                        id="attachments" name="attachments[]" multiple
                                        accept=".pdf,.jpg,.jpeg,.png,.gif"><label class="custom-file-label"
                                        for="attachments">{{ translate('Upload Files') }}</label></div>
                                @if (isset($payment) && $payment->attachments)
                                    <div class="mt-2">
                                        @foreach (json_decode($payment->attachments, true) as $file)
                                            <a href="{{ Storage::url($file) }}"
                                                target="_blank">{{ basename($file) }}</a><br>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-md-7 pr-3">
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th width="40"><input type="checkbox" id="select_all_invoices"
                                                onchange="toggleAllInvoices()"></th>
                                        <th>{{ translate('Date') }}</th>
                                        <th>{{ translate('Invoice No') }}</th>
                                        <th class="text-right">{{ translate('Due Amount') }}</th>
                                        <th class="text-right">{{ translate('Pay Amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="due_invoices_body" style="max-height: 280px; overflow-y: auto;">
                                    <tr>
                                        <td colspan="5" class="text-center py-3">{{ translate('Select a supplier') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="modal-footer px-0 justify-content-start pt-4 border-0">
                    <button type="button" class="btn btn-secondary"
                        onclick="window.location.href='{{ route('accounts.supplier-payments.index') }}'">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn-account-color" id="save_btn">{{ translate('Save') }}</button>
                    {{-- <button type="button" class="btn-account-color" id="save_and_new_btn" onclick="saveAndNew()">{{ translate('Save & New') }}</button> --}}
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('.select2-single').select2();
            loadSupplierDue();
            togglePaymentMethod();
        });

        function validateForm() {
            let allocations = $('.invoice_checkbox:checked').length;
            let paymentMethod = $('#payment_method').val();
            let amount = parseFloat($('#amount').val()) || 0;
            let dueAmount = parseFloat($('#supplier_due_amount').val()) || 0;

            let isValid = true;

            if (allocations === 0) {
                isValid = false;
            }

            if (paymentMethod === 'MBANKING' && !$('#transaction_no').val()) {
                isValid = false;
            }

            if (amount <= 0 || amount > dueAmount) {
                isValid = false;
            }

            // Enable/disable save buttons
            $('#save_btn, #save_and_new_btn').prop('disabled', !isValid);

            return isValid;
        }

        // Call this function on various events
        $(document).on('change input',
            '#supplierPaymentForm input, #supplierPaymentForm select, #supplierPaymentForm textarea', validateForm);
        $(document).on('change', '.invoice_checkbox', validateForm);

        function loadSupplierDue() {
            let supplierId = $('#supplier_id').val();
            if (supplierId) {
                let url = "{{ route('accounts.supplier-payments.due', ['supplierId' => '__ID__']) }}".replace('__ID__',
                    supplierId);
                $.get(url, function(res) {
                    if (res.success) {
                        $('#supplier_due_display').text('Total Due: ' + parseFloat(res.due_amount).toFixed(2) +
                            ' ৳');
                        $('#supplier_due_amount').val(res.due_amount);
                        $('#amount').attr('max', res.due_amount);
                        renderInvoices(res.invoices);
                    }
                });
            } else {
                $('#due_invoices_body').html(
                    '<tr><td colspan="5" class="text-center py-3">{{ translate('Select a supplier') }}</td></tr>');
            }
        }

        function renderInvoices(invoices) {
            if (!invoices || !invoices.length) {
                $('#due_invoices_body').html(
                    '<tr><td colspan="5" class="text-center py-3">{{ translate('No due invoices') }}</td></tr>');
                return;
            }
            let html = '';
            invoices.sort((a, b) => new Date(b.date) - new Date(a.date));
            $.each(invoices, function(i, inv) {
                let due = parseFloat(inv.due_amount);
                html += `<tr>
            <td class="text-center"><input type="checkbox" class="invoice_checkbox" data-id="${inv.id}" data-amount="${due}"></td>
            <td>${inv.date}</td><td>${inv.invoice_no}</td>
            <td class="text-right">${due.toFixed(2)}</td>
            <td class="text-right"><input type="number" step="0.01" class="form-control form-control-sm text-right invoice_payment_amount" value="${due.toFixed(2)}" disabled data-max="${due}"></td>
        </tr>`;
            });
            $('#due_invoices_body').html(html);
        }

        $(document).on('change', '.invoice_checkbox', function() {
            let row = $(this).closest('tr');
            let input = row.find('.invoice_payment_amount');
            let isChecked = $(this).is(':checked');
            let amount = parseFloat(input.val()) || 0;
            input.prop('disabled', !isChecked);
            let total = parseFloat($('#amount').val()) || 0;
            if (isChecked) $('#amount').val((total + amount).toFixed(2));
            else $('#amount').val((total - amount).toFixed(2));
            validateAmount();
        });

        $(document).on('input', '.invoice_payment_amount', function() {
            let row = $(this).closest('tr');
            if (!row.find('.invoice_checkbox').is(':checked')) return;
            let max = parseFloat($(this).data('max')) || 0;
            let val = parseFloat($(this).val()) || 0;
            if (val > max) {
                val = max;
                $(this).val(val.toFixed(2));
            }
            let total = 0;
            $('.invoice_checkbox:checked').each(function() {
                let r = $(this).closest('tr');
                total += parseFloat(r.find('.invoice_payment_amount').val()) || 0;
            });
            $('#amount').val(total.toFixed(2));
            validateAmount();
        });

        $('#amount').on('input', function() {
            let target = parseFloat($(this).val()) || 0;
            let dueTotal = parseFloat($('#supplier_due_amount').val()) || 0;
            if (target > dueTotal) {
                target = dueTotal;
                $(this).val(target.toFixed(2));
            }
            let rows = [];
            $('#due_invoices_body tr').each(function() {
                let due = parseFloat($(this).find('td:eq(3)').text()) || 0;
                rows.push({
                    row: $(this),
                    due: due
                });
            });
            let remaining = target;
            for (let i = 0; i < rows.length; i++) {
                let r = rows[i];
                let checkbox = r.row.find('.invoice_checkbox');
                let input = r.row.find('.invoice_payment_amount');
                if (remaining <= 0) {
                    if (checkbox.is(':checked')) checkbox.prop('checked', false).trigger('change');
                } else {
                    let pay = Math.min(remaining, r.due);
                    if (pay > 0) {
                        if (!checkbox.is(':checked')) checkbox.prop('checked', true);
                        input.val(pay.toFixed(2));
                        input.prop('disabled', false);
                        remaining -= pay;
                    } else {
                        if (checkbox.is(':checked')) checkbox.prop('checked', false).trigger('change');
                    }
                }
            }
            validateAmount();
        });

        function togglePaymentMethod() {
            let method = $('#payment_method').val();
            let subType = '';
            if (method === 'CASH') subType = 'cash';
            else if (method === 'BANK') subType = 'bank';
            else if (method === 'MBANKING') subType = 'mobile_bank';
            else {
                $('#account_row, #mobile_banking_fields').hide();
                return;
            }
            $('#account_row').show();
            $('#mobile_banking_fields').toggle(method === 'MBANKING');
            $('#transaction_no').prop('required', method === 'MBANKING');
            let url = "{{ route('accounts.supplier-payments.accounts', ['subType' => '__SUBTYPE__']) }}".replace(
                '__SUBTYPE__', subType);
            $.get(url, function(accounts) {
                let select = $('#account_head_id');
                select.empty().append('<option value="">{{ translate('Select Account') }}</option>');
                $.each(accounts, function(i, acc) {
                    select.append(
                        `<option value="${acc.id}" data-balance="${acc.balance}">${acc.name} (Balance: ${parseFloat(acc.balance).toFixed(2)})</option>`
                    );
                });
                let old = "{{ old('account_head_id', isset($payment) ? $payment->payment_account_id : '') }}";
                if (old) select.val(old);
            });
        }

        function checkAccountBalance() {
            let method = $('#payment_method').val();
            if (method === 'CASH' || method === 'BANK' || method === 'MBANKING') {
                let selected = $('#account_head_id option:selected');
                let balance = parseFloat(selected.data('balance')) || 0;
                let amount = parseFloat($('#amount').val()) || 0;
                if (amount > balance) $('#balance_error').show();
                else $('#balance_error').hide();
            }
        }

        function validateAmount() {
            let amount = parseFloat($('#amount').val()) || 0;
            let due = parseFloat($('#supplier_due_amount').val()) || 0;
            if (amount > due) {
                alert('{{ translate('Payment amount cannot exceed due amount!') }}');
                $('#amount').val(due);
                $('#amount').trigger('input');
            }
            checkAccountBalance();
        }

        function toggleAllInvoices() {
            let isChecked = $('#select_all_invoices').is(':checked');
            $('.invoice_checkbox').each(function() {
                let cb = $(this);
                let row = cb.closest('tr');
                let input = row.find('.invoice_payment_amount');
                let due = parseFloat(row.find('td:eq(3)').text()) || 0;
                if (isChecked) {
                    cb.prop('checked', true);
                    input.val(due.toFixed(2));
                    input.prop('disabled', false);
                } else {
                    cb.prop('checked', false);
                    input.prop('disabled', true);
                }
            });
            let total = 0;
            $('.invoice_checkbox:checked').each(function() {
                let r = $(this).closest('tr');
                total += parseFloat(r.find('.invoice_payment_amount').val()) || 0;
            });
            $('#amount').val(total.toFixed(2));
            validateAmount();
        }

        // function saveAndNew() { $('#supplierPaymentForm').attr('data-save-new', 'true'); $('#supplierPaymentForm').submit(); }

        // $('#supplierPaymentForm').on('submit', function(e) {
        //     e.preventDefault();
        //     let allocations = [];
        //     $('.invoice_checkbox:checked').each(function() {
        //         let row = $(this).closest('tr');
        //         allocations.push({ purchase_id: $(this).data('id'), paid_amount: parseFloat(row.find('.invoice_payment_amount').val()) || 0 });
        //     });
        //     let formData = new FormData(this);
        //     formData.append('allocations', JSON.stringify(allocations));
        //     $.ajax({
        //         url: $(this).attr('action'),
        //         type: 'POST',
        //         data: formData,
        //         processData: false,
        //         contentType: false,
        //         success: function(res) {
        //             if(res.success) {
        //                 if($('#supplierPaymentForm').attr('data-save-new') === 'true') {
        //                     $('#supplierPaymentForm')[0].reset();
        //                     $('#supplier_id').val(null).trigger('change');
        //                     $('#payment_method').val('CASH').trigger('change');
        //                     $('#supplierPaymentForm').removeAttr('data-save-new');
        //                     toastr.success(res.message);
        //                 } else {
        //                     window.location.href = "{{ route('accounts.supplier-payments.index') }}";
        //                 }
        //             } else toastr.error(res.message);
        //         },
        //         error: function(xhr) { toastr.error(xhr.responseJSON?.message || 'Error'); }
        //     });
        // });
        $('#supplierPaymentForm').on('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            // Collect allocations from checked invoices
            let allocations = [];
            $('.invoice_checkbox:checked').each(function() {
                let row = $(this).closest('tr');
                let paidAmount = parseFloat(row.find('.invoice_payment_amount').val()) || 0;

                if (paidAmount > 0) {
                    allocations.push({
                        purchase_id: $(this).data('id'),
                        paid_amount: paidAmount
                    });
                }
            });

            // Validate allocations before submitting
            if (allocations.length === 0) {
                toastr.error('{{ translate('Please select at least one invoice and enter payment amount.') }}');
                return false;
            }

            let totalAllocated = allocations.reduce((sum, alloc) => sum + parseFloat(alloc.paid_amount), 0);
            let paymentAmount = parseFloat($('#amount').val()) || 0;

            if (Math.abs(totalAllocated - paymentAmount) > 0.01) {
                toastr.error('{{ translate('Allocated amounts must equal total payment amount.') }}');
                return false;
            }

            // Prepare form data
            let formData = new FormData(this);
            formData.append('allocations', JSON.stringify(allocations));

            // Add method spoofing for updates
            if ($('input[name="_method"]').val() === 'PUT') {
                formData.append('_method', 'PUT');
            }

            // Show loading state
            let saveBtn = $('#save_btn');
            let originalText = saveBtn.text();
            saveBtn.prop('disabled', true).text('{{ translate('Saving...') }}');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(res) {
                    if (res.success) {
                        toastr.success(res.message);
                        setTimeout(function() {
                            window.location.href = res.redirect ||
                                "{{ route('accounts.supplier-payments.index') }}";
                        }, 1000);
                    } else {
                        toastr.error(res.message);
                        saveBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr) {
                    saveBtn.prop('disabled', false).text(originalText);

                    if (xhr.status === 422) {
                        let response = xhr.responseJSON;
                        if (response.errors) {
                            // Show validation errors
                            let errors = response.errors;
                            for (let key in errors) {
                                if (errors.hasOwnProperty(key)) {
                                    toastr.error(errors[key][0]);
                                }
                            }
                        } else if (response.message) {
                            toastr.error(response.message);
                        }
                    } else if (xhr.status === 500) {
                        toastr.error(xhr.responseJSON?.message ||
                            '{{ translate('Server error occurred') }}');
                    } else {
                        toastr.error('{{ translate('An unexpected error occurred') }}');
                    }
                }
            });
        });
    </script>
@endsection
