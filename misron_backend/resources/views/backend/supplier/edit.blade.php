@extends('backend.layouts.app')

@section('content')

<div class="container">
<h1 class="text-center">Supplier Edit</h1>
<div class="card p-4">
    <form action="{{ route('supplier.update',$supplier->id) }}" method="POST">
        @csrf
        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="business_name">Business Name:</label>
                <input type="text" name="business_name" class="form-control mb-2" id="business_name" value="{{ $supplier->business_name ?? '' }}">
            </div>
            <div class="form-group col-md-6 d-none">
                <label for="contact_id">Contact ID:</label>
                <input type="text" name="contact_id" class="form-control mb-2" id="contact_id" value="{{ $supplier->contact_id ?? '' }}">
            </div>
            <div class="form-group col-md-6">
                <label for="mobile">Mobile:</label>
                <input type="number" name="mobile" class="form-control mb-2" id="mobile" value="{{ $supplier->mobile ?? '' }}">
            </div>
            <div class="form-group col-md-6 d-none">
                <label for="opening_balance">Opening Balance:</label>
                <input type="number" name="opening_balance" class="form-control mb-2" id="opening_balance" readonly value="{{ $supplier->opening_balance ?? '0' }}">
            </div>
            {{-- <div class="form-group col-md-6">
                <label for="opening_balance">Opening Balance:</label>
                <input type="number" name="opening_balance" class="form-control mb-2" id="opening_balance" readonly value="{{ $supplier->opening_balance ?? '0' }}">
            </div> --}}
             <div class="form-group col-md-6">
                <label for="opening_balance">Opening Balance:</label>
                <input type="number" name="opening_balance" class="form-control mb-2" id="opening_balance" readonly value="{{ $supplier->opening_balance ?? '0' }}">
            </div>
            <div class="form-group col-md-6">
                {{-- @dd($supplier->due_amount); --}}
                <label for="opening_balance">Due Amount:</label>
                <input type="number"class="form-control mb-2" readonly value="{{ $supplier->due_amount ?? '0' }}">
            </div>


            <div class="form-group col-md-12">
                <label for="address">Address:</label>
                <input type="text" name="address" class="form-control mb-2" id="address" value="{{ $supplier->address ?? '' }}">
            </div>
            <div class="form-group col-md-6">
                <label for="contact_person_name">Contact Person Name:</label>
                <input type="text" name="contact_person_name" class="form-control mb-2" id="contact_person_name" value="{{ $supplier->contact_person_name ?? ''}}">
            </div>
            <div class="form-group col-md-6">
                <label for="email">Email:</label>
                <input type="email" name="email" class="form-control mb-2" id="email" value="{{ $supplier->email ?? ''}}">
            </div>
            <div class="form-group col-md-6">
                <label for="tax_number">Tax Number:</label>
                <input type="text" name="tax_number" class="form-control mb-2" id="tax_number" value="{{ $supplier->tax_number ?? '' }}">
            </div>
            <div class="form-group col-md-6">
                <label for="pay_term">Pay Term:</label>
                <select name="pay_term" class="form-control mb-2" id="pay_term">
                    <option value="cash" {{ old('pay_term', $supplier->pay_term) == 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="bill_to_bill" {{ old('pay_term', $supplier->pay_term) == 'bill_to_bill' ? 'selected' : '' }}>Bill To Bill</option>
                    <option value="days" {{ old('pay_term', $supplier->pay_term) == 'days' ? 'selected' : '' }}>Days</option>
                    <option value="monthly" {{ old('pay_term', $supplier->pay_term) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="after_sales" {{ old('pay_term', $supplier->pay_term) == 'after_sales' ? 'selected' : '' }}>After Sales</option>
                </select>
            </div>
        </div>
        <div class="text-right">
            <button type="submit" class="btn btn-primary">Update</button>
        </div>
    </form>
</div>
</div>

@endsection
