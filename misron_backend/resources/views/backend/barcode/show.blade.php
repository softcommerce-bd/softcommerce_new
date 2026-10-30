@extends('backend.layouts.app')

@section('content')
    <div class="container">
        <h2>Show Barcode</h2>
        <div class="form-group">
            <strong>Product Name:</strong>
            {{ $barcode->product_name }}
        </div>
        <div class="form-group">
            <strong>Barcode Number:</strong>
            {{ $barcode->barcode_number }}
        </div>
        <div class="form-group">
            <strong>Barcode:</strong>
            {!! DNS1D::getBarcodeHTML($barcode->barcode_number, $barcode->barcode_type) !!}
        </div>
        <a href="{{ route('barcodes.index') }}" class="btn btn-secondary">Back</a>
    </div>
@endsection
