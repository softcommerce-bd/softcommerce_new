@extends('backend.layouts.app')

@section('content')
<div class="container">
    <h2>Edit Barcode</h2>
    <form action="{{ route('barcodes.update', $barcode->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="product_name">Product Name:</label>
            <input type="text" name="product_name" class="form-control" value="{{ $barcode->product_name }}" required>
        </div>
        <div class="form-group">
            <label for="barcode_number">Barcode Number:</label>
            <input type="text" name="barcode_number" class="form-control" value="{{ $barcode->barcode_number }}" required>
        </div>
        <div class="form-group">
            <label for="barcode_type">Barcode Type:</label>
            <select name="barcode_type" class="form-control">
                <option value="C39" {{ $barcode->barcode_type == 'C39' ? 'selected' : '' }}>C39</option>
                <option value="C128" {{ $barcode->barcode_type == 'C128' ? 'selected' : '' }}>C128</option>
                <!-- Add other types as needed -->
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Update</button>
    </form>
</div>
@endsection
