@php
    $__baseline = \App\Services\Accounting\LedgerBalances::baselineAt();
    $__uncosted = \Illuminate\Support\Facades\Schema::hasColumn('order_details', 'cost_status') ? (int) \Illuminate\Support\Facades\DB::table('order_details')->where('cost_status', 'uncosted')->count() : 0;
@endphp
<div class="alert alert-info small py-2" role="note" id="ledger-source-note">
    @if ($__baseline)
        {{ translate('Source: one ledger. Movements before') }} {{ \Carbon\Carbon::parse($__baseline)->format('d M Y H:i') }} {{ translate('come from the account statement history; everything after comes from the journal (every old-screen posting is journalled too).') }}
    @else
        {{ translate('Source: account statement history. Journal reconciliation has not started (no baseline), so this report is as accurate as the old screens\' own statement rows. Balances for past dates are the current balance minus statement rows dated later; amounts the old screens never logged cannot be dated.') }}
    @endif
</div>
@if ($__uncosted > 0)
    <div class="alert alert-warning small py-2" role="alert" id="uncosted-note">
        {{ $__uncosted }} {{ translate('dispatched order line(s) have an unknown cost (no FIFO cost layer). Their sales are included but no cost or profit is claimed for them, so profit here is NOT final and may be overstated. Resolve with the opening-cost process; history is never rewritten.') }}
    </div>
@endif
