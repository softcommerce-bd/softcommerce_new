<!-- backend/accounts/partials/income_content.blade.php -->
<div class="w-100">
    <!-- Sales Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Sales') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $salesAccounts = $incomeAccounts->where('sub_type', 'sales'); @endphp
            @forelse($salesAccounts as $account)
                <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                    <div class="col d-flex align-items-center">
                        <span class="lbl">{{ $account->name }} ({{ $account->current_balance ?? '' }})</span>
                    </div>
                    <div class="col-1 px-1 d-flex align-items-center">
                        @if ($account->is_deleted == 1)
                            <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)"
                                onclick="editAccount({{ $account->id }})" title="Edit">
                                <i class="las la-edit text-primary"></i>
                            </a>
                            <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)"
                                onclick="deleteAccount({{ $account->id }})" title="Delete">
                                <i class="las la-trash text-danger"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                    <div class="col text-center text-muted">{{ translate('No sales accounts found') }}</div>
                </div>
            @endforelse
        </div>

        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('sales', 'Sales')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Sales') }}
            </button>
        </div>
    </div>

    <!-- Income Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Income') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $incomeOnlyAccounts = $incomeAccounts->where('sub_type', 'income'); @endphp
            @forelse($incomeOnlyAccounts as $account)
                <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                    <div class="col d-flex align-items-center">
                        <span class="lbl">{{ $account->name }} ({{ $account->current_balance ?? '' }})</span>
                    </div>
                    <div class="col-1 px-1 d-flex align-items-center">
                        @if ($account->is_deleted == 1)
                            <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)"
                                onclick="editAccount({{ $account->id }})" title="Edit">
                                <i class="las la-edit text-primary"></i>
                            </a>
                            <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)"
                                onclick="deleteAccount({{ $account->id }})" title="Delete">
                                <i class="las la-trash text-danger"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                    <div class="col text-center text-muted">{{ translate('No income accounts found') }}</div>
                </div>
            @endforelse
        </div>

        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('income', 'Income')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Income') }}
            </button>
        </div>
    </div>

    <!-- Revenue Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Revenue') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $revenueAccounts = $incomeAccounts->where('sub_type', 'revenue'); @endphp
            @forelse($revenueAccounts as $account)
                <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                    <div class="col d-flex align-items-center">
                        <span class="lbl">{{ $account->name }} ({{ $account->current_balance ?? '' }})</span>
                    </div>
                    <div class="col-1 px-1 d-flex align-items-center">
                        @if ($account->is_deleted == 1)
                            <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)"
                                onclick="editAccount({{ $account->id }})" title="Edit">
                                <i class="las la-edit text-primary"></i>
                            </a>
                            <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)"
                                onclick="deleteAccount({{ $account->id }})" title="Delete">
                                <i class="las la-trash text-danger"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                    <div class="col text-center text-muted">{{ translate('No revenue accounts found') }}</div>
                </div>
            @endforelse
        </div>

        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('revenue', 'Revenue')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Revenue') }}
            </button>
        </div>
    </div>
</div>
