<div class="row">
    <div class="col-md-12">
        <div class="card data-table-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="las la-balance-scale text-primary mr-2"></i>{{ translate('Balance Sheet') }}</h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-soft-primary" onclick="printBalanceSheet()">
                        <i class="las la-print"></i> {{ translate('Print') }}
                    </button>
                    <button type="button" class="btn btn-sm btn-soft-info" onclick="exportBalanceSheet()">
                        <i class="las la-file-excel"></i> {{ translate('Export') }}
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Left Column: Assets -->
                    <div class="col-md-6">
                        <div class="balance-sheet-card">
                            <div class="balance-sheet-header">
                                <h5 class="mb-0 text-white">{{ translate('Assets') }}</h5>
                            </div>
                            
                            <!-- Current Assets -->
                            <div class="balance-sheet-section">
                                <div class="balance-sheet-row bg-light">
                                    <strong>{{ translate('Current Assets') }}</strong>
                                    <strong>{{ number_format($currentAssetsTotal ?? 0, 2) }} ৳</strong>
                                </div>
                                
                                @foreach($currentAssets ?? [] as $asset)
                                <div class="balance-sheet-row pl-4">
                                    <span>{{ $asset->name }}</span>
                                    <span>{{ number_format($asset->current_balance, 2) }} ৳</span>
                                </div>
                                @endforeach
                            </div>
                            
                            <!-- Bank Accounts -->
                            <div class="balance-sheet-section mt-3">
                                <div class="balance-sheet-row bg-light">
                                    <strong>{{ translate('Bank & Cash') }}</strong>
                                    <strong>{{ number_format($bankCashTotal ?? 0, 2) }} ৳</strong>
                                </div>
                                
                                @foreach($bankAccounts?? [] as $account)
                                <div class="balance-sheet-row pl-4">
                                    <span>{{ $account->name }}</span>
                                    <span>{{ number_format($account->current_balance, 2) }} ৳</span>
                                </div>
                                @endforeach
                            </div>
                            
                            <!-- Fixed Assets -->
                            <div class="balance-sheet-section mt-3">
                                <div class="balance-sheet-row bg-light">
                                    <strong>{{ translate('Fixed Assets') }}</strong>
                                    <strong>{{ number_format($fixedAssetsTotal ?? 0, 2) }} ৳</strong>
                                </div>
                                
                                @foreach($fixedAssets ?? [] as $asset)
                                <div class="balance-sheet-row pl-4">
                                    <span>{{ $asset->name }}</span>
                                    <span>{{ number_format($asset->current_balance, 2) }} ৳</span>
                                </div>
                                @endforeach
                            </div>
                            
                            <!-- Total Assets -->
                            <div class="balance-sheet-row total mt-3">
                                <strong>{{ translate('Total Assets') }}</strong>
                                <strong class="text-success">{{ number_format($totalAssets ?? 0, 2) }} ৳</strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column: Liabilities & Equity -->
                    <div class="col-md-6">
                        <!-- Liabilities -->
                        <div class="balance-sheet-card">
                            <div class="balance-sheet-header">
                                <h5 class="mb-0 text-white">{{ translate('Liabilities & Equity') }}</h5>
                            </div>
                            
                            <!-- Current Liabilities -->
                            <div class="balance-sheet-section">
                                <div class="balance-sheet-row bg-light">
                                    <strong>{{ translate('Current Liabilities') }}</strong>
                                    <strong>{{ number_format($currentLiabilitiesTotal ?? 0, 2) }} ৳</strong>
                                </div>
                                
                                @foreach($currentLiabilities ?? [] as $liability)
                                <div class="balance-sheet-row pl-4">
                                    <span>{{ $liability->name }}</span>
                                    <span>{{ number_format($liability->current_balance, 2) }} ৳</span>
                                </div>
                                @endforeach
                            </div>
                            
                            <!-- Total Liabilities -->
                            <div class="balance-sheet-row total mt-2">
                                <strong>{{ translate('Total Liabilities') }}</strong>
                                <strong class="text-danger">{{ number_format($totalLiabilities ?? 0, 2) }} ৳</strong>
                            </div>
                            
                            <!-- Equity -->
                            <div class="balance-sheet-section mt-3">
                                <div class="balance-sheet-row bg-light">
                                    <strong>{{ translate('Equity') }}</strong>
                                    <strong>{{ number_format($totalEquity ?? 0, 2) }} ৳</strong>
                                </div>
                                
                                @foreach($equityAccounts ?? [] as $equity)
                                <div class="balance-sheet-row pl-4">
                                    <span>{{ $equity->name }}</span>
                                    <span>{{ number_format($equity->current_balance, 2) }} ৳</span>
                                </div>
                                @endforeach
                            </div>
                            
                            <!-- Total Liabilities & Equity -->
                            <div class="balance-sheet-row total mt-3">
                                <strong>{{ translate('Total Liabilities & Equity') }}</strong>
                                <strong class="text-primary">{{ number_format(($totalLiabilities ?? 0) + ($totalEquity ?? 0), 2) }} ৳</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportBalanceSheet() {
    window.location.href = '{{ route("accounts.balance-sheet.export") }}';
}
</script>