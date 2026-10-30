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
                    <span class="font-weight-bold text-uppercase small text-secondary">{{ translate('Navigation') }}</span>
                </div>
            </div>
            <ul class="nav flex-column p-3" role="tablist">
                <li class="nav-item module-header">{{ translate('ACCOUNTING') }}</li>
                
                <li class="nav-item">
                    <a class="nav-link {{ Request::routeIs('accounts.index') && !Request::get('tab') ? 'active' : '' }}" 
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
            </ul>
        </div>

        <!-- RIGHT: CONTENT AREA -->
        <div class="flex-grow-1">
            @yield('accounts_content')
        </div>
    </div>
</div>
@endsection