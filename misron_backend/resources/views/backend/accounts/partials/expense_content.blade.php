<!-- backend/accounts/partials/expense_content.blade.php -->
<div class="w-100">
    <!-- Expense Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Expense') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $expenseOnlyAccounts = isset($expenseAccounts) ? $expenseAccounts->where('sub_type', 'expense') : collect(); @endphp
            @forelse($expenseOnlyAccounts as $account)
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }} ({{ $account->current_balance ?? '' }})</span>
                </div>
                <div class="col-1 px-1 d-flex align-items-center">
                    @if($account->is_deleted == 1)
                        <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="editAccount({{ $account->id }})" title="Edit">
                            <i class="las la-edit text-primary"></i>
                        </a>
                        <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="deleteAccount({{ $account->id }})" title="Delete">
                            <i class="las la-trash text-danger"></i>
                        </a>
                    @endif
                </div>
            </div>
            @empty
            <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                <div class="col text-center text-muted">{{ translate('No expense accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('expense', 'Expense')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Expense') }}
            </button>
        </div>
    </div>

    <!-- Purchase Section -->
    {{-- <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 5px;">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Purchase') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $purchaseAccounts = $expenseAccounts->where('sub_type', 'purchase'); @endphp
            @forelse($purchaseAccounts as $account)
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }}</span>
                </div>
                <div class="col-1 px-1 d-flex align-items-center">
                    <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="editAccount({{ $account->id }})" title="Edit">
                        <i class="las la-edit text-primary"></i>
                    </a>
                </div>
            </div>
            @empty
            <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                <div class="col text-center text-muted">{{ translate('No purchase accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('purchase', 'Purchase')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Purchase') }}
            </button>
        </div>
    </div> --}}

    <!-- Cost Of Sales Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Cost Of Sales') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $costOfSalesAccounts = $expenseAccounts->where('sub_type', 'cost_of_sales'); @endphp
            @forelse($costOfSalesAccounts as $account)
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }} ({{ $account->current_balance ?? '' }})</span>
                </div>
                 <div class="col-1 px-1 d-flex align-items-center">
                    @if($account->is_deleted == 1)
                        <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="editAccount({{ $account->id }})" title="Edit">
                            <i class="las la-edit text-primary"></i>
                        </a>
                        <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="deleteAccount({{ $account->id }})" title="Delete">
                            <i class="las la-trash text-danger"></i>
                        </a>
                    @endif
                </div>
            </div>
            @empty
            <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                <div class="col text-center text-muted">{{ translate('No cost of sales accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        {{-- <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('cost_of_sales', 'Cost Of Sales')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Cost Of Sales') }}
            </button>
        </div> --}}
    </div>
</div>