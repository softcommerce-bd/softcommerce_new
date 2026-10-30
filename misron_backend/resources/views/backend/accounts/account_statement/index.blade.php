<div class="card">
    <div class="card-header border-0">
        <h5 class="mb-0">{{ translate('Account Statement') }}</h5>
    </div>

    <div class="card-body">
        {{-- Filter Form (GET method, reloads page) --}}
        <form method="GET" action="{{ route('accounts.index') }}">
            <input type="hidden" name="tab" value="account-statement">
            <div class="form-row align-items-end">
                <div class="col-md-3 mb-3">
                    <label>{{ translate('Date Range') }}</label>
                    <input type="text" class="form-control aiz-date-range" id="filter_date_range" name="date_range"
                           value="{{ $selectedDateRange ?? '' }}"
                           placeholder="{{ translate('Select date') }}" data-format="DD-MM-Y" data-separator=" to "
                           data-advanced-range="true" autocomplete="off">
                </div>

                <div class="col-md-3 mb-3">
                    <label>{{ translate('Account') }}</label>
                    <select class="form-control aiz-selectpicker" id="filter_account_id" name="account_id">
                        <option value="">{{ translate('Select Account') }}</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" {{ ($selectedAccount ?? '') == $account->id ? 'selected' : '' }}>
                                {{ $account->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 mb-3">
                    <button type="submit" class="bg-account-color text-white border-0 w-100 py-2">
                        {{ translate('Filter') }}
                    </button>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table class="table aiz-table mb-0">
                <thead class="bg-account-color text-white">
                    <tr>
                        <th width="15%">{{ translate('Date') }}</th>
                        <th width="20%">{{ translate('Voucher No') }}</th>
                        <th width="25%">{{ translate('Reference') }}</th>
                        <th width="13%" class="text-right">{{ translate('In (৳)') }}</th>
                        <th width="13%" class="text-right">{{ translate('Out (৳)') }}</th>
                        <th width="14%" class="text-right">{{ translate('Balance (৳)') }}</th>
                    </tr>
                </thead>
               <tbody>
    <tr class="bg-light">
        <td colspan="5" class="text-right font-weight-bold">{{ translate('Previous Balance :') }}</td>
        <td class="text-right font-weight-bold">৳ {{ number_format($previousBalance, 2) }}</td>
    </tr>
    {!! $rowsHtml !!}
</tbody>
            </table>
        </div>
    </div>
</div>

@section('script')
<script>
    // Initialise AIZ selectpicker and date range picker
    $(document).ready(function() {
        if (typeof AIZ !== 'undefined' && AIZ.plugins) {
            if (AIZ.plugins.selectPicker) {
                AIZ.plugins.selectPicker();
            }
            if (AIZ.plugins.aizDateRange) {
                AIZ.plugins.aizDateRange();
            }
        }
    });
</script>
@endsection