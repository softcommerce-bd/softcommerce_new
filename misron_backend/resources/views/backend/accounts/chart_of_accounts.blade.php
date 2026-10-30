<div class="card">
    <div class="card-header">
        <h5 class="mb-0 fw-600"><i class="las la-chart-pie text-primary mr-2"></i>{{ translate('Chart of Accounts') }}</h5>
    </div>
    <div class="card-body">
        
        <!-- 4 Tabs for Chart of Accounts -->
        <ul class="nav nav-tabs mb-4" id="chartOfAccountsTabs">
            <li class="nav-item">
                <a class="nav-link active" id="asset-main-tab" href="javascript:void(0)" onclick="switchCOATab('asset')">
                    <i class="las la-building"></i> {{ translate('Asset') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="liability-main-tab" href="javascript:void(0)" onclick="switchCOATab('liability')">
                    <i class="las la-hand-holding-usd"></i> {{ translate('Liability') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="income-main-tab" href="javascript:void(0)" onclick="switchCOATab('income')">
                    <i class="las la-tag"></i> {{ translate('Income') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="expense-main-tab" href="javascript:void(0)" onclick="switchCOATab('expense')">
                    <i class="las la-receipt"></i> {{ translate('Expense') }}
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div id="chartOfAccountsTabContent">
            <!-- ASSET CONTENT -->
            <div id="assetMainContent" class="coa-tab-pane active">
                @include('backend.accounts.partials.asset_content', [
                    'assetAccounts' => $assetAccounts ?? []
                ])
            </div>
            <!-- LIABILITY CONTENT -->
            <div id="liabilityMainContent" class="coa-tab-pane" >
                @include('backend.accounts.partials.liability_content', [
                    'liabilityAccounts' => $liabilityAccounts ?? []
                ])
            </div>
            <!-- INCOME CONTENT -->
            <div id="incomeMainContent" class="coa-tab-pane" >
                @include('backend.accounts.partials.income_content', [
                    'incomeAccounts' => $incomeAccounts ?? []
                ])
            </div>
            <!-- EXPENSE CONTENT -->
            <div id="expenseMainContent" class="coa-tab-pane">
                @include('backend.accounts.partials.expense_content', [
                    'expenseAccounts' => $expenseAccounts ?? []
                ])
            </div>
        </div>
    </div>
</div>

<style>
.nav-tabs .nav-link.active {
    background-color: var(--account-color);
    color: white;
    border: none;
}
.nav-tabs .nav-link {
    color: #4a5568;
    font-weight: 500;
    cursor: pointer;
}
.nav-tabs .nav-link:hover {
    border-color: #e2e8f0 #e2e8f0 #dee2e6;
    isolation: isolate;
}
.coa-tab-pane {
    display: none;
}
.coa-tab-pane.active {
    display: block !important;
}

</style>
<script>
function switchCOATab(tabName) {
    // Update tab buttons
    document.querySelectorAll('#chartOfAccountsTabs .nav-link').forEach(tab => {
        tab.classList.remove('active');
    });
    let activeTab = document.getElementById(tabName + '-main-tab');
    if (activeTab) activeTab.classList.add('active');
    
    // Update panes - remove active class from all, add to target
    document.querySelectorAll('.coa-tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    let activePane = document.getElementById(tabName + 'MainContent');
    if (activePane) activePane.classList.add('active');
}

// Initialize chart of accounts tabs when the parent tab becomes visible
$(document).ready(function() {
    // Listen for when the Chart of Accounts parent tab is shown
    $('a[href="#chartOfAccounts"]').on('shown.bs.tab', function() {
        switchCOATab('asset');
    });
    
    // Also check if chart of accounts is already visible on page load (direct hash access)
    if (window.location.hash === '#chartOfAccounts') {
        // Small delay to ensure DOM is ready
        setTimeout(function() {
            switchCOATab('asset');
        }, 100);
    }
});

// Global functions for account operations
function openAccountModal(subType, sectionName) {
    $('#modal_account_sub_type').val(subType);
    $('#modal_account_type').val('asset');
    $('#accountModalLabel').text('{{ translate("Create New Account") }} - ' + sectionName);
    $('#accountModalForm')[0].reset();
    $('#modal_opening_balance').val(0);
    $('#modal_as_of_date').val(new Date().toISOString().slice(0,10));
    $('#is_sub_account').prop('checked', false);
    $('#parent_account_group').hide();
    $('#accountModal').modal('show');
}

function editAccount(id) {
    $.ajax({
        url: 'accounts/chart-of-accounts/' + id + '/edit',
        type: 'GET',
        success: function(response) {
            if (response.success) {
                showNotification('info', 'Edit account: ' + response.data.name);
            }
        },
        error: function() {
            showNotification('danger', 'Error loading account data');
        }
    });
}

function viewStatement(accountId) {
    window.location.href = '/accounts/statement/' + accountId;
}

function deleteAccount(id) {
    if (confirm('{{ translate("Are you sure?") }}')) {
        $.ajax({
            url: '/accounts/chart-of-accounts/delete/' + id,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' },
            success: function(response) {
                if (response.success) {
                    location.reload();
                    showNotification('success', response.message);
                }
            },
            error: function(xhr) {
                showNotification('danger', xhr.responseJSON?.message || 'Error deleting account');
            }
        });
    }
}
</script>