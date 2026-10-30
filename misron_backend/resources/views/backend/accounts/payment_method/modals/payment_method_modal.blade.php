<div class="modal fade" id="paymentMethodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Create New Payment Type') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="paymentMethodForm">
                @csrf
                <input type="hidden" id="payment_method_id" name="method_id">
                <input type="hidden" id="deposit_type" name="deposit_type" value="auto">
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Payment Type') }} <span class="text-danger">*</span></label>
                        <select class="form-control" id="payment_type" name="type" required>
                            <option value="">{{ translate('Select Payment Type') }}</option>
                            <option value="card">{{ translate('Card Payment') }}</option>
                            <option value="mobile_bank">{{ translate('Mobile Banking') }}</option>
                            <option value="online">{{ translate('Online Payment') }}</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="method_name" name="name" placeholder="e.g., Islami Bank, Visa Card" required>
                    </div>
                    
                    <div class="form-group" id="bank_account_group">
                        <label>{{ translate('Account') }} <span class="text-danger">*</span></label>
                        <select class="form-control" id="bank_account_id" name="bank_account_id">
                            <option value="">{{ translate('Select Bank Account') }}</option>
                            @foreach($bankAccounts ?? [] as $account)
                            <option value="{{ $account->id }}">{{ $account->name }} ({{ number_format($account->current_balance, 2) }} ৳)</option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ translate('Payments will be auto-deposited to this bank account') }}</small>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Charge') }} (%)</label>
                        <input type="number" step="0.01" class="form-control" id="method_charge" name="charge" placeholder="0.00" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Status') }}</label>
                        <select class="form-control" id="method_status" name="status">
                            <option value="active">{{ translate('Active') }}</option>
                            <option value="inactive">{{ translate('Inactive') }}</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Description') }}</label>
                        <textarea class="form-control" id="method_description" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn-account-color">{{ translate('Save Payment Method') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>