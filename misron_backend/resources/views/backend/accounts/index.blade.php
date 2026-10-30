@extends('backend.accounts.layout')

@section('accounts_content')
    @php
        $activeTab = Request::get('tab', 'dashboard');
    @endphp

    @if($activeTab == 'dashboard')
    @include('backend.accounts.dashboard')
    @elseif($activeTab == 'chart-of-accounts')
        @include('backend.accounts.chart_of_accounts')
    @elseif($activeTab == 'account-statement')
        @include('backend.accounts.account_statement.index')
    @elseif($activeTab == 'payment-methods')
        @include('backend.accounts.payment_methods.index')
    @elseif($activeTab == 'supplier-payments')
        @include('backend.accounts.supplier-payments.index')
    @elseif($activeTab == 'customer-payments')
        @include('backend.accounts.customer-payments.index')
    @elseif($activeTab == 'vouchers')
        @include('backend.accounts.vouchers.index')
    @elseif($activeTab == 'withdraw-deposit')
        @include('backend.accounts.withdraw_transfer.index')
    @elseif($activeTab == 'expenses')
        @include('backend.accounts.payment_expense.index')
    @elseif($activeTab == 'incomes')
        @include('backend.accounts.income.index')
    @else
        @include('backend.accounts.dashboard')
    @endif
@endsection