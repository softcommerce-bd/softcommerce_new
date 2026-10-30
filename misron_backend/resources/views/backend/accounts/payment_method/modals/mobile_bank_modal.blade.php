<div class="modal fade" id="mobileBankModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Create Mobile Bank Account') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="mobileBankForm">
                @csrf
                <input type="hidden" id="mobile_bank_id" name="account_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Mobile Bank Name') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mobile_bank_name" name="name" placeholder="e.g., bKash, Nagad, Rocket" required>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Mobile Number') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="mobile_number" name="mobile_number" placeholder="e.g., 018XXXXXXXX" required>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Opening Balance') }}</label>
                        <input type="number" step="0.01" class="form-control" id="opening_balance_mb" name="opening_balance" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('Cashout Charge') }} (%)</label>
                        <input type="number" step="0.01" class="form-control" id="cashout_charge_mb" name="cashout_charge" value="0">
                        <small class="text-muted">{{ translate('Charge percentage for cashout (e.g., 1.85 for 1.85%)') }}</small>
                    </div>
                    
                    <div class="form-group">
                        <label>{{ translate('As of Date') }}</label>
                        <input type="date" class="form-control" id="as_of_date_mb" name="as_of_date" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="bg-account-color text-white border-0 p-2">{{ translate('Save Account') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>