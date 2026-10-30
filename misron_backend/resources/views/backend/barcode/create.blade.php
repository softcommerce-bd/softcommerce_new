@extends('backend.layouts.app')

@section('content')
    <div class="container">
        <h2>Create Barcode</h2>
        <form action="{{ route('barcodes.store') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="product_name">Product Name:</label>
                <input type="text" name="product_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="barcode_number">Barcode Number:</label>
                <input type="text" name="barcode_number" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="barcode_type">Barcode Type:</label>
                <select name="barcode_type" class="form-control">
                    <option value="C39">C39</option>
                    <option value="C128">C128</option>
                    <!-- Add other types as needed -->
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create</button>
        </form>
    </div>
@endsection
