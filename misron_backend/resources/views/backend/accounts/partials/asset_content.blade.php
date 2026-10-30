<!-- backend/accounts/partials/asset_content.blade.php -->
<div class="w-100">
    
    <!-- Cash Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Cash') }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Current Balance(৳)') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $cashAccounts = isset($assetAccounts) ? $assetAccounts->where('sub_type', 'cash') : collect(); @endphp
            @forelse($cashAccounts as $account)
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }}</span>
                </div>
                <div class="col d-flex align-items-center">
                    <span class="lbl text-success font-weight-bold">{{ number_format($account->current_balance, 2) }}</span>
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
                <div class="col text-center text-muted">{{ translate('No cash accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('cash', 'Cash')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Cash') }}
            </button>
        </div>
    </div>

    <!-- Bank Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Bank') }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Current Balance(৳)') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $bankAccountsList = isset($assetAccounts) ? $assetAccounts->where('sub_type', 'bank') : collect(); @endphp
            @forelse($bankAccountsList as $account)
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }}</span>
                    @if($account->bank_name)
                        <small class="text-muted ml-2">({{ $account->bank_name }} - {{ $account->account_number }})</small>
                    @endif
                </div>
                <div class="col d-flex align-items-center">
                    <span class="lbl text-success font-weight-bold">{{ number_format($account->current_balance, 2) }}</span>
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
                <div class="col text-center text-muted">{{ translate('No bank accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('bank', 'Bank')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Bank') }}
            </button>
        </div>
    </div>

    <!-- Mobile Bank Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Mobile Bank') }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Current Balance(৳)') }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Cash Out Balance(৳)') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
            @php $mobileBankAccounts = isset($assetAccounts) ? $assetAccounts->where('sub_type', 'mobile_bank') : collect(); @endphp
            @forelse($mobileBankAccounts as $account)
            <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                <div class="col d-flex align-items-center">
                    <span class="lbl">{{ $account->name }}</span>
                    @if($account->mobile_number)
                        <small class="text-muted ml-2">({{ $account->mobile_number }})</small>
                    @endif
                </div>
                <div class="col d-flex align-items-center">
                    <span class="lbl text-success font-weight-bold">{{ number_format($account->current_balance, 2) }}</span>
                </div>
                <div class="col d-flex align-items-center">
                    @php
                        $cashoutBalance = $account->current_balance / (1 + ($account->cashout_charge / 100));
                    @endphp
                    <span class="lbl text-warning">{{ number_format($cashoutBalance, 2) ?? 0.00 }}</span>
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
                <div class="col text-center text-muted">{{ translate('No mobile bank accounts found') }}</div>
            </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('mobile_bank', 'Mobile Bank')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Mobile Bank') }}
            </button>
        </div>
    </div>

    <!-- Receivable Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Receivable') }}</span>
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
                $receivableAccounts = isset($assetAccounts) ? $assetAccounts->where('sub_type', 'receivable') : collect();
                // Step 1: Get courier-wise totals
                // $courierTotals = \App\Models\Order::whereNotIn('delivery_status', ['pending', 'cancelled'])
                //     ->where(function ($q) {
                //         $q->where('payment_status', '!=', 'paid')
                //         ->orWhereNull('payment_status');
                //     })
                //     ->selectRaw('courier_name, SUM(grand_total) as total')
                //     ->groupBy('courier_name')
                //     ->pluck('total', 'courier_name');

                // // Step 2: Get settlement charges (once)
                // $settlementCharges = [
                //     'steadfast' => \DB::table('steadfast_settings')->value('settlement_charge') ?? 0,
                //     'pathao'    => \DB::table('pathao_courier_settings')->value('settlement_charge') ?? 0,
                // ];

                // // Step 3: Calculate final receivable
                // $totalCustomerReceivable = 0;

                // foreach ($courierTotals as $courier => $total) {
                //     $chargePercent = $settlementCharges[$courier] ?? 0;
                //     $chargeAmount  = ($total * $chargePercent) / 100;
                //     $netAmount     = $total - $chargeAmount;

                //     $totalCustomerReceivable += $netAmount;
                // }
            @endphp
            @forelse($receivableAccounts as $account)
                @php
                    $displayBalance = $account->current_balance;
                    // if($account->name == 'Accounts Receivable') {
                    //     $displayBalance = $totalCustomerReceivable;
                    // }
                    $isDeleted = isset($account->is_deleted) ? $account->is_deleted : 0;
                @endphp
                <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
                    <div class="col d-flex align-items-center">
                        <span class="lbl">{{ $account->name }}</span>
                      
                    </div>
                    <div class="col d-flex align-items-center">
                        <span class="lbl text-success font-weight-bold">{{ number_format($displayBalance, 2) }}</span>
                    </div>
                    <div class="col-1 px-1 d-flex align-items-center">
                        @if($isDeleted == 1)
                            <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="editAccount({{ $account->id }})" title="Edit">
                                <i class="las la-edit text-primary"></i>
                            </a>
                            <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="deleteAccount({{ $account->id }})" title="Delete">
                                <i class="las la-trash text-danger"></i>
                            </a>
                        @endif
                        {{-- <a class="btn without-focus border-0 px-1 py-0" href="javascript:void(0)" onclick="viewStatement({{ $account->id }})" title="Statement">
                            <i class="las la-file-alt text-info"></i>
                        </a> --}}
                    </div>
                </div>
            @empty
                <div class="w-100 panel-list-items d-flex flex-wrap py-2">
                    <div class="col text-center text-muted">{{ translate('No receivable accounts found') }}</div>
                </div>
            @endforelse
        </div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('receivable', 'Receivable')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Receivable') }}
            </button>
        </div>
    </div>

    <!-- Inventory Section -->
    <div class="w-100 panel-elem pb-4">
        <div class="w-100 panel-heading d-flex flex-wrap border-bottom py-3 mb-2 bg-account-color">
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Inventory') }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Current Balance(৳)') }}</span>
            </div>
            <div class="col-1 px-1 d-flex align-items-center">
                <span class="lbl font-weight-bold text-white">{{ translate('Action') }}</span>
            </div>
        </div>

        <div class="w-100 pb-2 mb-1">
    @php $inventoryAccounts = isset($assetAccounts) ? $assetAccounts->where('sub_type', 'inventory') : collect(); @endphp
    @forelse($inventoryAccounts as $account)
        @php
            $displayBalance = $account->current_balance;
            if($account->name == 'Current Stock Value') {
                $displayBalance = $currentStockvalue ?? 0.00;
            }
        @endphp
        <div class="w-100 panel-list-items d-flex flex-wrap py-2 border-bottom">
            <div class="col d-flex align-items-center">
                <span class="lbl">{{ $account->name }}</span>
            </div>
            <div class="col d-flex align-items-center">
                <span class="lbl text-success font-weight-bold">{{ number_format($displayBalance, 2) }}</span>
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
            <div class="col text-center text-muted">{{ translate('No inventory accounts found') }}</div>
        </div>
    @endforelse
</div>
        
        <div class="w-100">
            <button class="btn btn-sm btn-soft-primary" onclick="openAccountModal('inventory', 'Inventory')" type="button">
                <i class="las la-plus"></i> {{ translate('Add New Inventory') }}
            </button>
        </div>
    </div>

    <!-- Total Assets -->
    {{-- <div class="alert alert-success mt-3">
        <strong>{{ translate('Total Assets') }}:</strong> 
        {{ number_format(isset($assetAccounts) ? $assetAccounts->sum('current_balance') : 0, 2) }} ৳
    </div> --}}
</div>