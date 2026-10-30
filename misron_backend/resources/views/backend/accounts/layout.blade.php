@extends('backend.layouts.app')

@section('content')
    @include('backend.accounts.style')

    <div class="page-content">

        <!-- Main Layout: Sidebar + Content -->
        <div class="d-flex flex-column flex-md-row">

            <!-- LEFT: SIDE NAVIGATION -->
            <div class="page-side-nav flex-shrink-0 mb-4" style="width: 280px;">
                <div class="px-3 py-3 border-bottom bg-light-50">
                    <div class="d-flex align-items-center">
                        <i class="las la-compass la-lg text-primary mr-2"></i>
                        <span
                            class="font-weight-bold text-uppercase small text-secondary">{{ translate('Navigation') }}</span>
                    </div>
                </div>
                <ul class="nav flex-column p-3">
                    <li class="nav-item module-header">{{ translate('ACCOUNTING') }}</li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab', 'dashboard') == 'dashboard' ? 'active' : '' }}"
                            href="{{ route('accounts.index') }}">
                            <i class="las la-chart-pie"></i> {{ translate('Dashboard') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'chart-of-accounts' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'chart-of-accounts']) }}">
                            <i class="las la-chart-line"></i> {{ translate('Chart of Accounts') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'account-statement' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'account-statement']) }}">
                            <i class="las la-balance-scale"></i> {{ translate('Account Statement') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'payment-methods' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'payment-methods']) }}">
                            <i class="las la-money-bill"></i> {{ translate('Payment Methods') }}
                        </a>
                    </li>

                    <li class="nav-item module-header mt-3">{{ translate('PAYMENTS') }}</li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'supplier-payments' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'supplier-payments']) }}">
                            <i class="las la-truck"></i> {{ translate('Supplier Payment') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'customer-payments' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'customer-payments']) }}">
                            <i class="las la-user-check"></i> {{ translate('Customer Payment') }}
                        </a>
                    </li>

                    <li class="nav-item module-header mt-3">{{ translate('TRANSACTIONS') }}</li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'vouchers' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'vouchers']) }}">
                            <i class="las la-file-alt"></i> {{ translate('Vouchers') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'withdraw-deposit' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'withdraw-deposit']) }}">
                            <i class="las la-exchange-alt"></i> {{ translate('Withdraw/Deposit/Transfer/Cash Out') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'expenses' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'expenses']) }}">
                            <i class="las la-receipt"></i> {{ translate('Pay Expense') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::get('tab') == 'incomes' ? 'active' : '' }}"
                            href="{{ route('accounts.index', ['tab' => 'incomes']) }}">
                            <i class="las la-chart-line"></i> {{ translate('Incomes') }}
                        </a>
                    </li>




                    <li class="nav-item module-header mt-3">{{ translate('FINANCE REPORTS') }}</li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('accounts.reports.profit-loss') ? 'active' : '' }}"
                            href="{{ route('accounts.reports.profit-loss') }}">
                            <i class="las la-file-invoice"></i> {{ translate('Profit Loss Statement') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('accounts.reports.today-transactions') ? 'active' : '' }}"
                            href="{{ route('accounts.reports.today-transactions') }}">
                            <i class="las la-list-alt"></i> {{ translate('Today\'s Transactions') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('accounts.reports.cash-book') ? 'active' : '' }}"
                            href="{{ route('accounts.reports.cash-book') }}">
                            <i class="las la-book"></i> {{ translate('Cash Book Report') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('accounts.reports.day-closing') ? 'active' : '' }}"
                            href="{{ route('accounts.reports.day-closing') }}">
                            <i class="las la-calendar-check"></i> {{ translate('Day Closing') }}
                        </a>
                    </li>


                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('accounts.reports.balance-sheet') ? 'active' : '' }}"
                            href="{{ route('accounts.reports.balance-sheet') }}">
                            <i class="las la-balance-scale"></i> {{ translate('Balance Sheet') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('accounts.reports.trial-balance') ? 'active' : '' }}"
                            href="{{ route('accounts.reports.trial-balance') }}">
                            <i class="las la-table"></i> {{ translate('Trial Balance') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('accounts.reports.expense-detail') ? 'active' : '' }}"
                            href="{{ route('accounts.reports.expense-detail') }}">
                            <i class="las la-file-invoice-dollar"></i> {{ translate('Expense Detail') }}
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ Request::routeIs('accounts.reports.expense-summary') ? 'active' : '' }}"
                            href="{{ route('accounts.reports.expense-summary') }}">
                            <i class="las la-chart-bar"></i> {{ translate('Expense Summary') }}
                        </a>
                    </li>
                </ul>
            </div>

            <!-- RIGHT: CONTENT AREA -->
            <div class="flex-grow-1">
                @yield('accounts_content')
            </div>
        </div>
    </div>

    @include('backend.accounts.modals.account_modal')
    @include('backend.accounts.modals.bank_modal')
    @include('backend.accounts.modals.supplier_payment_modal')
    @include('backend.accounts.modals.customer_payment_modal')
    @include('backend.accounts.modals.statement_modal')
@endsection

@section('script')
    <script type="text/javascript">
        $(document).ready(function() {
            // Simple sidebar active state management
            $('.page-side-nav .nav-link').on('click', function() {
                // The page will reload naturally with the new URL
                // No extra JavaScript needed
            });
        });

        function showNotification(type, message) {
            if (typeof AIZ !== 'undefined' && AIZ.plugins && AIZ.plugins.notify) {
                AIZ.plugins.notify(type, message);
            } else {
                alert(type + ': ' + message);
            }
        }

        function printBalanceSheet() {
            window.open('{{ route('accounts.balance-sheet') }}', '_blank');
        }

        function printTrialBalance() {
            window.open('{{ route('accounts.trial-balance') }}', '_blank');
        }
    </script>
@endsection
