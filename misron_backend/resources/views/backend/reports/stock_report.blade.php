@extends('backend.layouts.app')

@section('content')
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ translate('Product wise stock report') }}</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 mx-auto">
            {{-- Summary --}}
            <div class="row g-3">
                {{-- Total Products --}}
                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                    <div
                        class="bg-soft-primary rounded-2 h-100 d-flex flex-column justify-content-center align-items-start text-primary p-3">
                        <p class="fs-13 fw-600 text-dark mb-1">{{ translate('Total Products') }}</p>
                        <h1 class="fs-24 fw-600 mb-0">{{ $total_products ?? '0' }}</h1>
                    </div>
                </div>

                {{-- Total Stock Qty --}}
                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                    <div
                        class="bg-soft-primary rounded-2 h-100 d-flex flex-column justify-content-center align-items-start text-primary p-3">
                        <p class="fs-13 fw-600 text-dark mb-1">{{ translate('Total Stock Qty') }}</p>
                        <h1 class="fs-24 fw-600 mb-0">{{ $total_stock_qty ?? '0' }}</h1>
                    </div>
                </div>

                {{-- Total Stock Value --}}
                <div class="col-12 col-sm-6 col-md-4 col-xl-4 mb-3">
                    <div
                        class="bg-soft-primary rounded-2 h-100 d-flex flex-column justify-content-center align-items-start text-primary p-3">
                        <p class="fs-13 fw-600 text-dark mb-1">{{ translate('Total Stock Value') }}</p>
                        <h1 class="fs-24 fw-600 mb-0">{{ single_price($total_stock_value) }}</h1>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    {{-- Filter Form --}}
                    <form action="{{ route('stock_report.index') }}" method="GET">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>{{ translate('Category') }}</label>
                                    <select class="form-control aiz-selectpicker" name="category_id">
                                        <option value="">{{ translate('All Categories') }}</option>

                                        @foreach (\App\Models\Category::all() as $category)
                                            {{-- @dd($category); --}}
                                            <option value="{{ $category->id }}"
                                                @if ($category_id == $category->id) selected @endif>
                                                {{ $category->getTranslation('name') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>{{ translate('Supplier') }}</label>
                                    <select class="form-control aiz-selectpicker" name="supplier_id">
                                        <option value="">{{ translate('All Suppliers') }}</option>
                                        @foreach (\App\Models\Supplier::all() as $supplier)
                                            <option value="{{ $supplier->id }}"
                                                @if ($supplier_id == $supplier->id) selected @endif>
                                                {{ $supplier->business_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>{{ translate('Purchase ID') }}</label>
                                    <input type="text" class="form-control" name="purchase_id"
                                        value="{{ $purchase_id }}" placeholder="{{ translate('Purchase ID') }}">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>{{ translate('Product ID') }}</label>
                                    <input type="text" class="form-control" name="product_id"
                                        value="{{ $product_id }}" placeholder="{{ translate('Product ID') }}">
                                </div>
                            </div>

                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="">Action</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button class="btn btn-primary w-100" type="submit">
                                                {{ translate('Filter') }}
                                            </button>
                                        </div>
                                        <div class="col-md-6">

                                            <a href="{{ route('stock_report.index') }}"
                                                class="btn btn-soft-secondary w-100">
                                                {{ translate('Clear') }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    {{-- Download Button --}}
                    <div class="text-right mb-3">
                        <a href="{{ route('stock_report.download', request()->all()) }}" class="btn btn-success">
                            <i class="las la-file-csv"></i> {{ translate('Export CSV') }}
                        </a>
                    </div>

                    {{-- Table --}}
                    <div class="table-responsive">
                        <table class="table table-bordered aiz-table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ translate('Product ID') }}</th>
                                    <th>{{ translate('Product Name') }}</th>
                                    <th>{{ translate('Category') }}</th>
                                    <th>{{ translate('Current Stock') }}</th>
                                    <th>{{ translate('Purchase Date') }}</th>
                                    <th>{{ translate('Supplier') }}</th>
                                    <th>{{ translate('Purchase Qty') }}</th>
                                    <th>{{ translate('Remaining Qty') }}</th>
                                    <th>{{ translate('Buying Price') }}</th>
                                    <th>{{ translate('Stock Value') }}</th>
                                    <th>{{ translate('Purchase ID') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $product)
                                    @if (count($product->purchase_details) > 0)
                                        @foreach ($product->purchase_details as $index => $detail)
                                            {{-- @dump($detail); --}}
                                            <tr>
                                                @if ($index === 0)
                                                    <td rowspan="{{ count($product->purchase_details) }}">
                                                        {{ $product->id }}</td>
                                                    <td rowspan="{{ count($product->purchase_details) }}">
                                                        {{ $product->getTranslation('name') }}</td>
                                                    <td rowspan="{{ count($product->purchase_details) }}">
                                                        {{ $product->main_category ? $product->main_category->getTranslation('name') : 'N/A' }}
                                                    </td>
                                                    <td rowspan="{{ count($product->purchase_details) }}" class="fw-bold">
                                                        {{ $product->stock_qty }}
                                                    </td>
                                                @endif
                                                <td>{{ $detail['purchase_date'] }}</td>
                                                <td>{{ $detail['supplier_name'] }}</td>
                                                <td>{{ $detail['purchase_quantity'] }}</td>
                                                <td>{{ $detail['remaining_qty'] }}</td>
                                                <td>{{ single_price($detail['buying_price']) }}</td>
                                                <td>{{ single_price($detail['total_value']) }}</td>
                                                <td>{{ $detail['purchase_id'] }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td>{{ $product->id }}</td>
                                            <td>{{ $product->getTranslation('name') }}</td>
                                            <td>{{ $product->main_category ? $product->main_category->getTranslation('name') : 'N/A' }}
                                            </td>
                                            <td class="fw-bold">{{ $product->stock_qty }}</td>
                                            <td colspan="7" class="text-center text-muted">
                                                {{ translate('No purchase data available') }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="aiz-pagination mt-4">
                        {{ $products->appends(request()->input())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
