<!-- backend/accounts/partials/liability_content.blade.php -->
<div class="w-100">
    <!-- Payable Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Payable') }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Current Balance(৳)') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php 
                $payableAccounts = isset($liabilityAccounts) ? $liabilityAccounts->where('sub_type', 'payable') : collect(); 
                 
               
            @endphp
            @forelse($payableAccounts as $account)
            @php
            $displayBalance =  number_format($account->current_balance, 2);
                //  if($account->name == 'Supplier Payable') {
                //          $displayBalance = $totalSupplierDueBalance;
                //     }
            @endphp
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }}</span>
                </div>
                <div class="col d-flex align-items-center">
                    <span class="lbl text-danger font-weight-bold">{{$displayBalance }}</span>
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
                <div class="col text-center text-muted">{{ translate('No payable accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('payable', 'Payable')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Payable') }}
            </button>
        </div>
    </div>

    <!-- Tax Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Tax') }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Current Balance(৳)') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $taxAccounts = $liabilityAccounts->where('sub_type', 'tax'); @endphp
            @forelse($taxAccounts as $account)
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }}</span>
                </div>
                <div class="col d-flex align-items-center">
                    <span class="lbl text-danger font-weight-bold">{{ number_format($account->current_balance, 2) }}</span>
                </div>
                <div class="col-1 px-1 d-flex align-items-center">
                    @if($account->is_deleted == 1)
                    <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="editAccount({{ $account->id }})" title="Edit">
                        <i class="las la-edit text-primary"></i>
                    </a>
                    {{-- <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="viewStatement({{ $account->id }})" title="Statement">
                        <i class="las la-file-alt text-info"></i>
                    </a> --}}
                    <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="deleteAccount({{ $account->id }})" title="Delete">
                        <i class="las la-trash text-danger"></i>
                    </a>
                    @endif
                </div>
            </div>
            @empty
            <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                <div class="col text-center text-muted">{{ translate('No tax accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('tax', 'Tax')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Tax') }}
            </button>
        </div>
    </div>

    <!-- Equity Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color" >
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Equity') }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Current Balance(৳)') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $equityAccounts = isset($liabilityAccounts) ? $liabilityAccounts->where('sub_type', 'equity') : collect(); @endphp
            @forelse($equityAccounts as $account)
             @php
             $displayBalance =  number_format($account->current_balance, 2);
                 if($account->name == 'Current Year Earnings') {
                         $displayBalance = $totalCurrentYearEarnings;
                    }
            @endphp
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }}</span>
                </div>
                <div class="col d-flex align-items-center">
                    <span class="lbl {{ $account->current_balance < 0 ? 'text-danger' : 'text-success' }} font-weight-bold">
                        {{ $displayBalance }}
                    </span>
                </div>
                <div class="col-1 px-1 d-flex align-items-center">
                    @if($account->is_deleted == 1)
                   
                    <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="editAccount({{ $account->id }})" title="Edit">
                        <i class="las la-edit text-primary"></i>
                    </a>
                    <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="deleteAccount({{ $account->id }})" title="Delete">
                        <i class="las la-trash text-danger"></i>
                    </a>
                    {{-- <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="viewStatement({{ $account->id }})" title="Statement">
                        <i class="las la-file-alt text-info"></i>
                    </a> --}}
                    @endif
                </div>
            </div>
            @empty
            <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                <div class="col text-center text-muted">{{ translate('No equity accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('equity', 'Equity')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Equity') }}
            </button>
        </div>
    </div>
</div>