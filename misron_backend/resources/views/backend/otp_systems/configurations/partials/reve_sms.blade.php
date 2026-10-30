<div class="col-lg-6">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0 h6">{{ translate('Reve SMS Credential') }}</h5>
        </div>
        <div class="card-body">
            <form class="form-horizontal" action="{{ route('update_credentials') }}" method="POST">
                <input type="hidden" name="otp_method" value="reve_sms">
                @csrf
                <div class="form-group row">
                    <input type="hidden" name="types[]" value="REVE_SMS_URL">
                    <div class="col-lg-3">
                        <label class="col-from-label">{{ translate('REVE SMS URL') }}</label>
                    </div>
                    <div class="col-lg-6">
                        <input type="text" class="form-control" name="REVE_SMS_URL" value="{{ env('REVE_SMS_URL') }}"
                            placeholder="https://smpp.revesms.com/smsapi" required>
                    </div>
                </div>
                <div class="form-group row">
                    <input type="hidden" name="types[]" value="REVE_SMS_APIKEY">
                    <div class="col-lg-3">
                        <label class="col-from-label">{{ translate('REVE SMS API KEY') }}</label>
                    </div>
                    <div class="col-lg-6">
                        <input type="text" class="form-control" name="REVE_SMS_APIKEY" value="{{ env('REVE_SMS_APIKEY') }}"
                            placeholder="REVE SMS API KEY" required>
                    </div>
                </div>
                <div class="form-group row">
                    <input type="hidden" name="types[]" value="REVE_SMS_SECRETKEY">
                    <div class="col-lg-3">
                        <label class="col-from-label">{{ translate('REVE SMS SECRET KEY') }}</label>
                    </div>
                    <div class="col-lg-6">
                        <input type="text" class="form-control" name="REVE_SMS_SECRETKEY" value="{{ env('REVE_SMS_SECRETKEY') }}"
                            placeholder="REVE SMS SECRET KEY" required>
                    </div>
                </div>
                <div class="form-group row">
                    <input type="hidden" name="types[]" value="REVE_SMS_CALLER_ID">
                    <div class="col-lg-3">
                        <label class="col-from-label">{{ translate('REVE SMS CALLER ID') }}</label>
                    </div>
                    <div class="col-lg-6">
                        <input type="text" class="form-control" name="REVE_SMS_CALLER_ID" value="{{ env('REVE_SMS_CALLER_ID') }}"
                            placeholder="REVE SMS CALLER ID" required>
                    </div>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
