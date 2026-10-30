@extends('backend.layouts.app')

@section('content')

    <div class="page-content">
        <div class="aiz-titlebar text-left mt-2 pb-2 px-3 px-md-2rem border-bottom border-gray">
            <div class="row align-items-center">
                <div class="col">
                    <h1 class="h3">{{ translate('Add New Product') }}</h1>
                </div>
                <div class="col text-right">
                    <a class="btn btn-xs btn-soft-primary" href="javascript:void(0);" onclick="clearTempdata()">
                        {{ translate('Clear Tempdata') }}
                    </a>
                </div>

            </div>
        </div>

        <div class="d-sm-flex">
            <!-- tab content -->
            <div class="flex-grow-1 p-sm-3 p-lg-2rem mb-2rem mb-md-0">
                <!-- Error Meassages -->
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Data type -->
                <input type="hidden" id="data_type" value="physical">

                <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data"
                    enctype="multipart/form-data" id="choice_form">
                    <div class="tab-content row">
                        @csrf
                        <div class="col-12 col-md-8">
                            <!-- General Section -->
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <!-- Product Information -->
                                <h5 class="mb-3 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Product Information') }}</h5>
                                <div class="w-100">
                                    <div class="row">
                                        <div class="col-xxl-12 col-xl-12">
                                            <!-- Product Name -->
                                            <div class="form-group">
                                                <label class="col-from-label fs-13">{{ translate('Product Name') }}
                                                    <span class="text-danger">*</span></label>
                                                <input type="text"
                                                    class="form-control @error('name') is-invalid @enderror" name="name"
                                                    value="{{ old('name') }}"
                                                    placeholder="{{ translate('Product Name') }}" onchange="update_sku()"
                                                    required>
                                            </div>
                                            <!-- Sales Tag Name -->
                                            <div class="form-group">
                                                <label class="col-from-label fs-13">{{ translate('Sales Tag Name') }}
                                                </label>
                                                <input type="text"
                                                    class="form-control @error('sales_name') is-invalid @enderror" name="sales_name"
                                                    value="{{ old('sales_name') }}"
                                                    placeholder="{{ translate('Sales Tag Name') }}"
                                                    >
                                            </div>

                                            <div class="row">
                                                <div class="col-12 col-md-6">
                                                    <!-- Brand -->
                                                    <div class="form-group" id="brand">
                                                        <label class="col-from-label fs-13">{{ translate('Brand') }}</label>
                                                        <select class="form-control aiz-selectpicker" name="brand_id"
                                                            id="brand_id" data-live-search="true">
                                                            <option value="">{{ translate('Select Brand') }}</option>
                                                            @foreach (\App\Models\Brand::all() as $brand)
                                                                <option value="{{ $brand->id }}"
                                                                    @selected(old('brand_id') == $brand->id)>
                                                                    {{ $brand->getTranslation('name') }}</option>
                                                            @endforeach
                                                        </select>
                                                        <small
                                                            class="text-muted">{{ translate("You can choose a brand if you'd like to display your product by brand.") }}</small>
                                                    </div>

                                                    <!-- Main Category Selection -->
                                                    <div class="form-group">
                                                        <label
                                                            class="col-from-label fs-13">{{ translate('Product Category') }}
                                                            <span class="text-danger">*</span></label>
                                                        <select class="form-control aiz-selectpicker" name="category_id"
                                                            id="main_category" data-live-search="true" required>
                                                            <option value="">{{ translate('Select Main Category') }}
                                                            </option>
                                                            @foreach ($categories as $category)
                                                                <option value="{{ $category->id }}"
                                                                    {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                                                    {{ $category->getTranslation('name') }}
                                                                </option>
                                                                @foreach ($category->childrenCategories as $childCategory)
                                                                    @include(
                                                                        'backend.product.products.category_option',
                                                                        [
                                                                            'child_category' => $childCategory,
                                                                            'level' => $childCategory->level,
                                                                            'selected_ids' => [],
                                                                        ]
                                                                    )
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                        @error('category_id')
                                                            <div class="text-danger">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-12 col-md-6">
                                                            @if (addon_is_activated('refund_request'))
                                                                <!-- refund_request -->
                                                                <div class="form-group mt-4 mb-4">
                                                                    <label
                                                                        class="col-from-label fs-13">{{ translate('Refundable') }}</label>
                                                                    <label class="aiz-switch aiz-switch-success mb-0 ml-4">
                                                                        <input type="checkbox" name="refundable" checked
                                                                            value="1">
                                                                        <span></span>
                                                                    </label>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div class="col-12 col-md-6">
                                                            <!-- Weight -->
                                                            <div class="form-group">
                                                                <label
                                                                    class="col-from-label fs-13">{{ translate('Weight') }}
                                                                    <small>({{ translate('In Kg') }})</small></label>
                                                                <input type="number" class="form-control" name="weight"
                                                                    value="{{ old('weight') ?? 0.0 }}" step="0.01"
                                                                    placeholder="0.00">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <br>

                                                    <!-- Tags -->
                                                    <div class="form-group">
                                                        <label class="col-from-label fs-13">{{ translate('Tags') }}</label>
                                                        <input type="text" class="form-control aiz-tag-input"
                                                            name="tags[]"
                                                            placeholder="{{ translate('Type and hit enter to add a tag') }}">
                                                        <small
                                                            class="text-muted">{{ translate('This is used for search. Input those words by which cutomer can find this product.') }}</small>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6">
                                                    <!-- Unit -->
                                                    <div class="form-group">
                                                        <label class="col-from-label fs-13">{{ translate('Unit') }}
                                                            <span class="text-danger">*</span></label>
                                                        <input type="text"
                                                            class="form-control @error('unit') is-invalid @enderror"
                                                            name="unit" value="{{ old('unit') }}"
                                                            placeholder="{{ translate('Unit (e.g. KG, Pc etc)') }}"
                                                            required>
                                                        <br>
                                                    </div>

                                                    <!-- Additional Categories Selection -->
                                                    <div class="form-group">
                                                        <label
                                                            class="col-from-label fs-13">{{ translate('Display Categories') }}
                                                            <span class="position-relative main-category-info-icon">
                                                                <i
                                                                    class="las la-question-circle fs-18 text-info float-right"></i>
                                                                <span
                                                                    class="main-category-info bg-soft-info p-2 position-absolute d-none border">{{ translate('This will be used for commission based calculations and homepage category wise product Show.') }}</span>
                                                            </span>
                                                        </label>
                                                        <select class="form-control aiz-selectpicker" name="category_ids[]"
                                                            id="additional_categories" data-live-search="true" multiple
                                                            data-selected-text-format="count">
                                                            @foreach ($categories as $category)
                                                                <option value="{{ $category->id }}"
                                                                    {{ collect(old('category_ids', []))->contains($category->id) ? 'selected' : '' }}>
                                                                    {{ $category->getTranslation('name') }}
                                                                </option>
                                                                @foreach ($category->childrenCategories as $childCategory)
                                                                    @include(
                                                                        'backend.product.products.category_option',
                                                                        [
                                                                            'child_category' => $childCategory,
                                                                            'level' => $childCategory->level,
                                                                            'selected_ids' => old(
                                                                                'category_ids',
                                                                                []),
                                                                        ]
                                                                    )
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                        @error('category_ids')
                                                            <div class="text-danger">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                    <!-- Hidden input to track that main is included -->
                                                    <input type="hidden" name="main_category_included"
                                                        id="main_category_included" value="0">

                                                    <!-- Minimum Purchase Qty -->
                                                    <div class="form-group">
                                                        <label
                                                            class="col-from-label fs-13">{{ translate('Minimum Purchase Qty') }}
                                                            <span class="text-danger">*</span></label>
                                                        <input type="number" lang="en"
                                                            class="form-control @error('min_qty') is-invalid @enderror"
                                                            name="min_qty" value="{{ old('min_qty') ?? 1 }}"
                                                            placeholder="minimun Quantity" min="1" required>
                                                        <small
                                                            class="text-muted">{{ translate('The minimum quantity needs to be purchased by your customer.') }}</small>
                                                    </div>

                                                    <!-- Maximum Purchase Qty -->
                                                    <div class="form-group">
                                                        <label
                                                            class="col-from-label fs-13">{{ translate('Maximum Purchase Qty') }}
                                                        </label>
                                                        <input type="number" lang="en"
                                                            class="form-control @error('max_qty') is-invalid @enderror"
                                                            name="max_qty"value="{{ old('max_qty', 0) }}"
                                                            placeholder="maximum Quantity">
                                                        <small
                                                            class="text-muted">{{ translate('The maximum quantity needs to be purchased by your customer.') }}</small>
                                                    </div>

                                                </div>
                                            </div>

                                            @if (addon_is_activated('pos_system'))
                                                <!-- Barcode -->
                                                <div class="form-group">
                                                    <label class="col-from-label fs-13">{{ translate('Barcode') }}</label>
                                                    <input type="text" class="form-control" name="barcode"
                                                        value="{{ old('barcode') }}"
                                                        placeholder="{{ translate('Barcode') }}">
                                                </div>
                                            @endif

                                        </div>

                                        <!-- Product Category -->

                                    </div>

                                    <!-- Description -->
                                    <div class="form-group">
                                        <label class="fs-13">{{ translate('Description') }}</label>
                                        <div class="">
                                            <textarea class="aiz-text-editor" name="description">{{ old('description') }}</textarea>
                                        </div>
                                    </div>

                                   <!-- Specification -->
                                    <div class="form-group">
                                        <label class="fs-13">{{ translate('Specification') }}</label>
                                        <div class="">
                                            <textarea class="aiz-text-editor" name="specification">{{ old('specification') }}</textarea>
                                        </div>
                                    </div>
                                    <!-- Question and Answer -->
                                    <div class="form-group">
                                        <label class="fs-13">{{ translate('Question & Answer') }}</label>
                                        <div class="">
                                            <textarea class="aiz-text-editor" name="question_answer">{{ old('question_answer') }}</textarea>
                                        </div>
                                    </div>

                                </div>

                                <!-- Flash Deal -->
                                {{-- <h5 class="mb-3 mt-4 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Flash Deal') }}
                                    <small
                                        class="text-muted">({{ translate('If you want to select this product as a flash deal, you can use it') }})</small>
                                </h5>
                                <div class="w-100">
                                    <!-- Add To Flash -->
                                    <div class="form-group">
                                        <label class="col-md-3 col-from-label">{{ translate('Add To Flash') }}</label>
                                        <select class="form-control aiz-selectpicker" name="flash_deal_id"
                                            id="flash_deal">
                                            <option value="">{{ translate('Choose Flash Title') }}</option>
                                            @foreach (\App\Models\FlashDeal::where('status', 1)->get() as $flash_deal)
                                                <option value="{{ $flash_deal->id }}">
                                                    {{ $flash_deal->title }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <!-- Discount -->
                                    <div class="form-group">
                                        <label class="col-md-3 col-from-label">{{ translate('Discount') }}</label>
                                        <input type="number" name="flash_discount" value="0" min="0"
                                            step="0.01" class="form-control">
                                    </div>
                                    <!-- Discount Type -->
                                    <div class="form-group">
                                        <label class="col-md-3 col-from-label">{{ translate('Discount Type') }}</label>
                                        <select class="form-control aiz-selectpicker" name="flash_discount_type"
                                            id="flash_discount_type">
                                            <option value="">{{ translate('Choose Discount Type') }}</option>
                                            <option value="amount">{{ translate('Flat') }}</option>
                                            <option value="percent">{{ translate('Percent') }}</option>
                                        </select>
                                    </div>
                                </div> --}}

                            </div>

                            {{-- Product pricing --}}
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <div class="bg-white">
                                    <!-- tab Title -->
                                    <h5 class="mb-3 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                        {{ translate('Product pricing') }}</h5>
                                    <div class="w-100">


                                        <div class="row">
                                            <div class="col-12 col-md-6">
                                                <!-- Unit price -->
                                                <div class="form-group">
                                                    <label class="col-from-label">{{ translate('Unit price') }} <span
                                                            class="text-danger">*</span></label>
                                                    <div class="col-md-12 px-0">
                                                        <input type="number" lang="en" min="0"
                                                            value="0" step="0.01"
                                                            placeholder="{{ translate('Unit price') }}" name="unit_price"
                                                            class="form-control @error('unit_price') is-invalid @enderror">
                                                    </div>
                                                </div>

                                                <!-- Discount -->
                                                <div class="form-group">
                                                    <label class="col-from-label">{{ translate('Discount') }} <span
                                                            class="text-danger">*</span></label>
                                                    <div class="row">
                                                        <div class="col-md-8">
                                                            <input type="number" lang="en" min="0"
                                                                value="0" step="0.01"
                                                                placeholder="{{ translate('Discount') }}" name="discount"
                                                                class="form-control @error('discount') is-invalid @enderror">
                                                        </div>
                                                        <div class="col-md-4">
                                                            <select class="form-control aiz-selectpicker"
                                                                name="discount_type">
                                                                <option value="amount" @selected(old('discount_type') == 'amount')>
                                                                    {{ translate('Flat') }}</option>
                                                                <option value="percent" @selected(old('discount_type') == 'percent')>
                                                                    {{ translate('Percent') }}</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Discount Date Range -->
                                                <div class="form-group">
                                                    <label class="control-label"
                                                        for="start_date">{{ translate('Discount Date Range') }}</label>
                                                    <div class="col-sm-12 px-0">
                                                        <input type="text" class="form-control aiz-date-range"
                                                            name="date_range"
                                                            placeholder="{{ translate('Select Date') }}"
                                                            data-time-picker="true" data-format="DD-MM-Y HH:mm:ss"
                                                            data-separator=" to " autocomplete="off">
                                                    </div>
                                                </div>

                                                <!-- Colors -->
                                                <div class="form-group gutters-5">
                                                    <div class="row pl-2">
                                                        <div class="col-auto">
                                                            {{-- <input type="text" class="form-control"
                                                                value="{{ translate('Colors') }}" disabled> --}}
                                                            <label class="control-label"
                                                                for="colors">{{ translate('Colors') }}</label>
                                                        </div>
                                                        <div class="col-1 px-0">
                                                            <label class="aiz-switch aiz-switch-success mb-0">
                                                                <input value="1" type="checkbox"
                                                                    name="colors_active">
                                                                <span></span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <select class="form-control aiz-selectpicker"
                                                            data-live-search="true" data-selected-text-format="count"
                                                            name="colors[]" id="colors" multiple disabled>
                                                            @foreach (\App\Models\Color::orderBy('name', 'asc')->get() as $key => $color)
                                                                <option value="{{ $color->code }}"
                                                                    data-content="<span><span class='size-15px d-inline-block mr-2 rounded border' style='background:{{ $color->code }}'></span><span>{{ $color->name }}</span></span>">
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <div id="show-hide-div">
                                                    <!-- Quantity -->
                                                    <div class="form-group">
                                                        <label class="col-from-label">{{ translate('Quantity') }} <span
                                                                class="text-danger">*</span></label>
                                                        <div class="col-md-12 px-0">
                                                            <input type="number" lang="en" min="0"
                                                                value="0" step="1"
                                                                placeholder="{{ translate('Quantity') }}"
                                                                name="current_stock" class="form-control" readonly>
                                                        </div>
                                                    </div>
                                                    <!-- SKU -->
                                                    <div class="form-group">
                                                        <label class="col-from-label">
                                                            {{ translate('SKU') }}
                                                        </label>
                                                        <div class="col-md-12 px-0">
                                                            <input type="text" placeholder="{{ translate('SKU') }}"
                                                                name="sku" value="{{ old('sku') }}"
                                                                class="form-control">
                                                        </div>
                                                    </div>

                                                </div>
                                                @if (addon_is_activated('club_point'))
                                                    <!-- club point -->
                                                    <div class="form-group">
                                                        <label class="col-from-label">
                                                            {{ translate('Set Point') }}
                                                        </label>
                                                        <div class="col-md-12 px-0">
                                                            <input type="number" lang="en" min="0"
                                                                value="0" step="1"
                                                                placeholder="{{ translate('1') }}" name="earn_point"
                                                                class="form-control">
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Attributes -->
                                                <div class="form-group gutters-5">
                                                    <div class="col-md-12">
                                                        <label class="control-label"
                                                            for="colors">{{ translate('Attributes') }}</label>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <select name="choice_attributes[]" id="choice_attributes"
                                                            class="form-control aiz-selectpicker"
                                                            data-selected-text-format="count" data-live-search="true"
                                                            multiple
                                                            data-placeholder="{{ translate('Choose Attributes') }}">
                                                            @foreach (\App\Models\Attribute::all() as $key => $attribute)
                                                                <option value="{{ $attribute->id }}">
                                                                    {{ $attribute->getTranslation('name') }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <p>{{ translate('Choose the attributes of this product and then input values of each attribute') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- choice options -->
                                        <div class="customer_choice_options row mb-4" id="customer_choice_options">

                                        </div>

                                        <!-- sku combination -->
                                        <div class="sku_combination" id="sku_combination">

                                        </div>

                                        <div class="row">
                                            <div class="col-12 col-md-6">
                                                <!-- External link -->
                                                <div class="form-group">
                                                    <label class="col-from-label">
                                                        {{ translate('External link') }}
                                                    </label>
                                                    <div class="col-md-12 px-0">
                                                        <input type="text" placeholder="{{ translate('External link') }}"
                                                            value="{{ old('external_link') }}" name="external_link"
                                                            class="form-control">
                                                        <small
                                                            class="text-muted">{{ translate('Leave it blank if you do not use external site link') }}</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <!-- External link button text -->
                                                <div class="form-group">
                                                    <label class="col-from-label">
                                                        {{ translate('External link button text') }}
                                                    </label>
                                                    <div class="col-md-12 px-0">
                                                        <input type="text"
                                                            placeholder="{{ translate('External link button text') }}"
                                                            name="external_link_btn" value="{{ old('external_link_btn') }}"
                                                            class="form-control">
                                                        <small
                                                            class="text-muted">{{ translate('Leave it blank if you do not use external site link') }}</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <br>
                                        </div>
                                        <!-- Search priority -->
                                        <div class="form-group">
                                            <label class="col-from-label">
                                                {{ translate('Search Priority Order') }}
                                            </label>
                                            <div class="col-md-12 px-0">
                                                <input type="text"
                                                    placeholder="{{ translate('Priority Order Number') }}"
                                                    name="search_order" value="{{ old('search_order') }}"
                                                    class="form-control">
                                                <small
                                                    class="text-muted">{{ translate('Provide the priority order for displaying the search box') }}</small>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Frequently Bought Product Section -->
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <h5 class="mb-3 pb-3 fs-17 fw-700">{{ translate('Frequently Bought') }}</h5>
                                <div class="w-100">
                                    <div class="d-flex mb-4">
                                        <div class="radio mar-btm mr-5 d-flex align-items-center">
                                            <input id="fq_bought_select_products" type="radio"
                                                name="frequently_bought_selection_type" value="product"
                                                onchange="fq_bought_product_selection_type()" checked>
                                            <label for="fq_bought_select_products"
                                                class="fs-14 fw-700 mb-0 ml-2">{{ translate('Select Product') }}</label>
                                        </div>
                                        <div class="radio mar-btm mr-3 d-flex align-items-center">
                                            <input id="fq_bought_select_category" type="radio"
                                                name="frequently_bought_selection_type" value="category"
                                                onchange="fq_bought_product_selection_type()">
                                            <label for="fq_bought_select_category"
                                                class="fs-14 fw-700 mb-0 ml-2">{{ translate('Select Category') }}</label>
                                        </div>
                                    </div>

                                    <div class="card">
                                        <div class="card-body">
                                            <div class="fq_bought_select_product_div">

                                                <div id="selected-fq-bought-products">

                                                </div>

                                                <button type="button"
                                                    class="btn btn-block border border-dashed hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center"
                                                    onclick="showFqBoughtProductModal()">
                                                    <i class="las la-plus"></i>
                                                    <span class="ml-2">{{ translate('Add More') }}</span>
                                                </button>
                                            </div>

                                            <div class="fq_bought_select_category_div d-none">
                                                <div class="form-group">
                                                    <label
                                                        class="col-md-2 col-from-label">{{ translate('Category') }}</label>
                                                    <div class="col-md-10">
                                                        <select class="form-control aiz-selectpicker"
                                                            data-placeholder="{{ translate('Select a Category') }}"
                                                            name="fq_bought_product_category_id" data-live-search="true"
                                                            required>
                                                            @foreach ($categories as $category)
                                                                <option value="{{ $category->id }}">
                                                                    {{ $category->getTranslation('name') }}</option>
                                                                @foreach ($category->childrenCategories as $childCategory)
                                                                    @include('categories.child_category', [
                                                                        'child_category' => $childCategory,
                                                                    ])
                                                                @endforeach
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="col-12 col-md-4">
                            {{-- Files & Media --}}
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <!-- Product Files & Media -->
                                <h5 class="mb-3 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Product Files & Media') }}</h5>
                                <div class="w-100">
                                    <!-- Gallery Images -->
                                    <div class="form-group">
                                        <label class="col-md-12 px-0 col-form-label"
                                            for="signinSrEmail">{{ translate('Gallery Images') }}
                                            <small>(600x600)</small></label>
                                        <div class="col-md-12 px-0">
                                            <div class="input-group" data-toggle="aizuploader" data-type="image"
                                                data-multiple="true">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text bg-soft-secondary font-weight-medium">
                                                        {{ translate('Browse') }}</div>
                                                </div>
                                                <div class="form-control file-amount">
                                                    {{ translate('Choose File') }}</div>
                                                <input type="hidden" name="photos" class="selected-files">
                                            </div>
                                            <div class="file-preview box sm">
                                            </div>
                                            <small
                                                class="text-muted">{{ translate('These images are visible in product details page gallery. Use 600x600 sizes images.') }}</small>
                                        </div>
                                    </div>
                                    <!-- Thumbnail Image -->
                                    <div class="form-group">
                                        <label class="col-md-12 px-0 col-form-label"
                                            for="signinSrEmail">{{ translate('Thumbnail Image') }}
                                            <small>(300x300)</small></label>
                                        <div class="col-md-12 px-0">
                                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text bg-soft-secondary font-weight-medium">
                                                        {{ translate('Browse') }}</div>
                                                </div>
                                                <div class="form-control file-amount">
                                                    {{ translate('Choose File') }}</div>
                                                <input type="hidden" name="thumbnail_img" class="selected-files">
                                            </div>
                                            <div class="file-preview box sm">
                                            </div>
                                            <small
                                                class="text-muted">{{ translate('This image is visible in all product box. Use 300x300 sizes image. Keep some blank space around main object of your image as we had to crop some edge in different devices to make it responsive.') }}</small>
                                        </div>
                                    </div>
                                </div>
                                <!-- Video Provider -->
                                <div class="form-group">
                                    <label class="col-md-12 px-0 col-from-label">{{ translate('Video Provider') }}</label>
                                    <div class="col-md-12 px-0">
                                        <select class="form-control aiz-selectpicker" name="video_provider"
                                            id="video_provider">
                                            <option value="youtube" @selected(old('video_provider') == 'youtube')>
                                                {{ translate('Youtube') }}</option>
                                            <option value="dailymotion" @selected(old('video_provider') == 'dailymotion')>
                                                {{ translate('Dailymotion') }}</option>
                                            <option value="vimeo" @selected(old('video_provider') == 'vimeo')>
                                                {{ translate('Vimeo') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- Video Link -->
                                <div class="form-group">
                                    <label class="col-md-12 px-0 col-from-label">{{ translate('Video Link') }}</label>
                                    <div class="col-md-12 px-0">
                                        <input type="text" class="form-control" name="video_link"
                                            value="{{ old('video_link') }}"
                                            placeholder="{{ translate('Video Link') }}">
                                        <small
                                            class="text-muted">{{ translate("Use proper link without extra parameter. Don't use short share link/embeded iframe code.") }}</small>
                                    </div>
                                </div>
                                <!-- custome Video Link -->
                                <div class="form-group row">
                                    <label class="col-md-3 col-from-label">{{ translate('Custom video') }} <small>(MP4)</small></label>
                                    <div class="col-md-9">
                                        <input 
                                            type="file" 
                                            name="custom_video" 
                                            id="custom_video" 
                                            class="form-control @error('custom_video') is-invalid @enderror"
                                            accept="video/mp4,video/x-m4v,video/*"
                                        >
                                        <small
                                            class="text-muted">{{ translate("Upload a video file (MP4 format)") }}</small>
                                    </div>
                                </div>
                                <!-- PDF Specification -->
                                <div class="form-group">
                                    <label class="col-md-12 px-0 col-form-label"
                                        for="signinSrEmail">{{ translate('PDF Specification') }}</label>
                                    <div class="col-md-12 px-0">
                                        <div class="input-group" data-toggle="aizuploader" data-type="document">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text bg-soft-secondary font-weight-medium">
                                                    {{ translate('Browse') }}</div>
                                            </div>
                                            <div class="form-control file-amount">{{ translate('Choose File') }}
                                            </div>
                                            <input type="hidden" name="pdf" class="selected-files">
                                        </div>
                                        <div class="file-preview box sm">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEO Section -->
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <!-- tab Title -->
                                <h5 class="mb-3 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('SEO Meta Tags') }}</h5>
                                <div class="w-100">
                                    <!-- Meta Title -->
                                    <div class="form-group">
                                        <label class="col-md-12 px-0 col-from-label">{{ translate('Meta Title') }}</label>
                                        <div class="col-md-12 px-0">
                                            <input type="text" class="form-control" name="meta_title"
                                                value="{{ old('meta_title') }}"
                                                placeholder="{{ translate('Meta Title') }}">
                                        </div>
                                    </div>
                                    <!-- Description -->
                                    <div class="form-group">
                                        <label class="col-md-12 px-0 col-from-label">{{ translate('Description') }}</label>
                                        <div class="col-md-12 px-0">
                                            <textarea name="meta_description" rows="8" class="form-control">{{ old('meta_description') }}</textarea>
                                        </div>
                                    </div>
                                    <!--Meta Image -->
                                    <div class="form-group">
                                        <label class="col-md-12 px-0 col-form-label"
                                            for="signinSrEmail">{{ translate('Meta Image') }}</label>
                                        <div class="col-md-12 px-0">
                                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                                <div class="input-group-prepend">
                                                    <div class="input-group-text bg-soft-secondary font-weight-medium">
                                                        {{ translate('Browse') }}</div>
                                                </div>
                                                <div class="form-control file-amount">{{ translate('Choose File') }}
                                                </div>
                                                <input type="hidden" name="meta_img" class="selected-files">
                                            </div>
                                            <div class="file-preview box sm">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Low Stock Quantity -->
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <h5 class="mb-3 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Low Stock Quantity Warning') }}</h5>
                                <div class="w-100 mb-3">
                                    <div class="form-group">
                                        <label class="col-md-12 px-0 col-from-label">
                                            {{ translate('Quantity') }}
                                        </label>
                                        <div class="col-md-12 px-0">
                                            <input type="number" name="low_stock_quantity" value="1"
                                                min="0" step="1" class="form-control">
                                        </div>
                                    </div>
                                </div>

                                <!-- Stock Visibility State -->
                                <h5 class="mb-3 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Stock Visibility State') }}</h5>
                                <div class="w-100">
                                    <!-- Show Stock Quantity -->
                                    <div class="form-group row">
                                        <label
                                            class="col-md-10 col-from-label">{{ translate('Show Stock Quantity') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0">
                                                <input type="radio" name="stock_visibility_state" value="quantity"
                                                    checked>
                                                <span></span>
                                            </label>
                                        </div>
                                    </div>
                                    <!-- Show Stock With Text Only -->
                                    <div class="form-group row">
                                        <label
                                            class="col-md-10 col-from-label">{{ translate('Show Stock With Text Only') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0">
                                                <input type="radio" name="stock_visibility_state" value="text">
                                                <span></span>
                                            </label>
                                        </div>
                                    </div>
                                    <!-- Hide Stock -->
                                    <div class="form-group row">
                                        <label class="col-md-10 col-from-label">{{ translate('Hide Stock') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0">
                                                <input type="radio" name="stock_visibility_state" value="hide">
                                                <span></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Vat & TAX -->
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <h5 class="mb-3 mt-4 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Vat & TAX') }}</h5>
                                <div class="w-100">
                                    @foreach (\App\Models\Tax::where('tax_status', 1)->get() as $tax)
                                        <label for="name">
                                            {{ $tax->name }}
                                            <input type="hidden" value="{{ $tax->id }}" name="tax_id[]">
                                        </label>

                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <input type="number" lang="en" min="0" value="0"
                                                    step="0.01" placeholder="{{ translate('Tax') }}" name="tax[]"
                                                    class="form-control">
                                            </div>
                                            <div class="form-group col-md-6">
                                                <select class="form-control aiz-selectpicker" name="tax_type[]">
                                                    <option value="amount">{{ translate('Flat') }}</option>
                                                    <option value="percent">{{ translate('Percent') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Promotional Option -->
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <!-- Status -->
                                <h5 class="mb-3 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Promotional Options') }}</h5>
                                <div class="w-100">
                                    <!-- Featured -->
                                    <div class="form-group row">
                                        <label class="col-md-10 col-from-label">{{ translate('Featured') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0 d-block">
                                                <input type="checkbox" name="featured" value="1">
                                                <span></span>
                                            </label>
                                        </div>
                                        <small
                                            class="col-8 text-muted">{{ translate('If you enable this, this product will be granted as a featured product.') }}</small>
                                    </div>
                                      <!-- Top Picks -->
                                    <div class="form-group row">
                                        <label class="col-md-10 col-from-label">{{ translate('Top Picks') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0 d-block">
                                                <input type="checkbox" name="top_pick" value="1">
                                                <span></span>
                                            </label>
                                        </div>
                                        <small
                                            class="col-8 text-muted">{{ translate('If you enable this, this product will be granted as a top picks product.') }}</small>
                                    </div>
                                    <!-- Trending Now -->
                                    <div class="form-group row">
                                        <label class="col-md-10 col-from-label">{{ translate('Trending Now') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0 d-block">
                                                <input type="checkbox" name="trending_now" value="1">
                                                <span></span>
                                            </label>
                                        </div>
                                        <small
                                            class="col-8 text-muted">{{ translate('If you enable this, this product will be granted as a Trending Now product.') }}</small>
                                    </div>
                                    <!-- Best Picks -->
                                    <div class="form-group row">
                                        <label class="col-md-10 col-from-label">{{ translate('Best Picks') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0 d-block">
                                                <input type="checkbox" name="best_pick" value="1">
                                                <span></span>
                                            </label>
                                        </div>
                                        <small
                                            class="col-8 text-muted">{{ translate('If you enable this, this product will be granted as a best picks product.') }}</small>
                                    </div>
                                    <!-- Seasonal Highlights -->
                                    <div class="form-group row">
                                        <label
                                            class="col-md-10 col-from-label">{{ translate('Seasonal Highlights') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0 d-block">
                                                <input type="checkbox" name="seasonal_highlight" value="1">
                                                <span></span>
                                            </label>
                                        </div>
                                        <small
                                            class="col-8 text-muted">{{ translate('If you enable this, this product will be granted as a seasonal Highlight product.') }}</small>
                                    </div>
                                    <!-- Todays Deal -->
                                    <div class="form-group row">
                                        <label class="col-md-10 col-from-label">{{ translate('Todays Deal') }}</label>
                                        <div class="col-md-auto">
                                            <label class="aiz-switch aiz-switch-success mb-0 d-block">
                                                <input type="checkbox" name="todays_deal" value="1">
                                                <span></span>
                                            </label>
                                        </div>
                                        <small
                                            class="col-8 text-muted">{{ translate('If you enable this, this product will be granted as a todays deal product.') }}</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Section -->
                            <div class="bg-white p-3 p-sm-2rem mb-4 rounded-3">
                                <!-- Shipping Configuration -->
                                <h5 class="mb-3 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Shipping Configuration') }}</h5>
                                <div class="w-100">
                                    <!-- Cash On Delivery -->
                                    @if (get_setting('cash_payment') == '1')
                                        <div class="form-group row">
                                            <label
                                                class="col-md-10 col-from-label">{{ translate('Cash On Delivery') }}</label>
                                            <div class="col-md-auto">
                                                <label class="aiz-switch aiz-switch-success mb-0">
                                                    <input type="checkbox" name="cash_on_delivery" value="1"
                                                        checked="">
                                                    <span></span>
                                                </label>
                                            </div>
                                        </div>
                                    @else
                                        <p>
                                            {{ translate('Cash On Delivery option is disabled. Activate this feature from here') }}
                                            <a href="{{ route('activation.index') }}"
                                                class="aiz-side-nav-link {{ areActiveRoutes(['shipping_configuration.index', 'shipping_configuration.edit', 'shipping_configuration.update']) }}">
                                                <span
                                                    class="aiz-side-nav-text">{{ translate('Cash Payment Activation') }}</span>
                                            </a>
                                        </p>
                                    @endif

                                    @if (get_setting('shipping_type') == 'product_wise_shipping')
                                        <!-- Free Shipping -->
                                        <div class="form-group">
                                            <label
                                                class="col-md-3 col-from-label">{{ translate('Free Shipping') }}</label>
                                            <div class="col-md-9">
                                                <label class="aiz-switch aiz-switch-success mb-0">
                                                    <input type="radio" name="shipping_type" value="free" checked>
                                                    <span></span>
                                                </label>
                                            </div>
                                        </div>
                                        <!-- Flat Rate -->
                                        <div class="form-group">
                                            <label class="col-md-3 col-from-label">{{ translate('Flat Rate') }}</label>
                                            <div class="col-md-9">
                                                <label class="aiz-switch aiz-switch-success mb-0">
                                                    <input type="radio" name="shipping_type" value="flat_rate">
                                                    <span></span>
                                                </label>
                                            </div>
                                        </div>
                                        <!-- Shipping cost -->
                                        <div class="flat_rate_shipping_div" style="display: none">
                                            <div class="form-group">
                                                <label
                                                    class="col-md-3 col-from-label">{{ translate('Shipping cost') }}</label>
                                                <div class="col-md-9">
                                                    <input type="number" lang="en" min="0" value="0"
                                                        step="0.01" placeholder="{{ translate('Shipping cost') }}"
                                                        name="flat_shipping_cost" class="form-control">
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Is Product Quantity Mulitiply -->
                                        <div class="form-group">
                                            <label
                                                class="col-md-3 col-from-label">{{ translate('Is Product Quantity Mulitiply') }}</label>
                                            <div class="col-md-9">
                                                <label class="aiz-switch aiz-switch-success mb-0">
                                                    <input type="checkbox" name="is_quantity_multiplied" value="1">
                                                    <span></span>
                                                </label>
                                            </div>
                                        </div>
                                    @else
                                        <p>
                                            {{ translate('Product wise shipping cost is disable. Shipping cost is configured from here') }}
                                            <a href="{{ route('shipping_configuration.index') }}"
                                                class="aiz-side-nav-link {{ areActiveRoutes(['shipping_configuration.index', 'shipping_configuration.edit', 'shipping_configuration.update']) }}">
                                                <span
                                                    class="aiz-side-nav-text">{{ translate('Shipping Configuration') }}</span>
                                            </a>
                                        </p>
                                    @endif
                                </div>

                                <!-- Estimate Shipping Time -->
                                <h5 class="mb-3 mt-4 pb-3 fs-17 fw-700" style="border-bottom: 1px dashed #e4e5eb;">
                                    {{ translate('Estimate Shipping Time') }}</h5>
                                <div class="w-100">
                                    <div class="form-group">
                                        <label class="col-md-3 col-from-label">{{ translate('Shipping Days') }}</label>
                                        <div class="col-md-9">
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="est_shipping_days"
                                                    value="{{ old('est_shipping_days') }}" min="1"
                                                    step="1" placeholder="{{ translate('Shipping Days') }}">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text"
                                                        id="inputGroupPrepend">{{ translate('Days') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>



                    </div>

                    <!-- Save Button -->
                    <div class="mt-4 text-right" style="display:flex">
                        <button type="submit" name="button" value="unpublish"
                            class="mx-2 btn btn-light w-230px btn-md rounded-2 fs-14 fw-700 shadow-secondary border-soft-secondary action-btn">{{ translate('Save & Unpublish') }}</button>
                        <button type="submit" name="button" value="publish"
                            class="mx-2 btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success action-btn">{{ translate('Save & Publish') }}</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

@endsection

@section('modal')
    <!-- Frequently Bought Product Select Modal -->
    @include('modals.product_select_modal')
@endsection

@section('script')
    <!-- Treeview js -->
    <script src="{{ static_asset('assets/js/hummingbird-treeview.js') }}"></script>

    <script type="text/javascript">
            function fq_bought_product_selection_type() {
                var productSelectionType = $("input[name='frequently_bought_selection_type']:checked").val();
                if (productSelectionType == 'product') {
                    $('.fq_bought_select_product_div').removeClass('d-none');
                    $('.fq_bought_select_category_div').addClass('d-none');
                } else if (productSelectionType == 'category') {
                    $('.fq_bought_select_category_div').removeClass('d-none');
                    $('.fq_bought_select_product_div').addClass('d-none');
                }
            }

            function showFqBoughtProductModal() {
                $('#fq-bought-product-select-modal').modal('show', {
                    backdrop: 'static'
                });
            }

            function filterFqBoughtProduct() {
                var searchKey = $('input[name=search_keyword]').val();
                var fqBroughCategory = $('select[name=fq_brough_category]').val();
                $.post('{{ route('product.search') }}', {
                    _token: AIZ.data.csrf,
                    product_id: null,
                    search_key: searchKey,
                    category: fqBroughCategory,
                    product_type: "physical"
                }, function(data) {
                    $('#product-list').html(data);
                    AIZ.plugins.fooTable();
                });
            }

            function addFqBoughtProduct() {
                var selectedProducts = [];
                $("input:checkbox[name=fq_bought_product_id]:checked").each(function() {
                    selectedProducts.push($(this).val());
                });

                var fqBoughtProductIds = [];
                $("input[name='fq_bought_product_ids[]']").each(function() {
                    fqBoughtProductIds.push($(this).val());
                });

                var productIds = selectedProducts.concat(fqBoughtProductIds.filter((item) => selectedProducts
                    .indexOf(item) <
                    0))

                $.post('{{ route('get-selected-products') }}', {
                    _token: AIZ.data.csrf,
                    product_ids: productIds
                }, function(data) {
                    $('#fq-bought-product-select-modal').modal('hide');
                    $('#selected-fq-bought-products').html(data);
                    AIZ.plugins.fooTable();
                });
            }
            
        $(document).ready(function() {
            // Initialize Select2/AIZ selectpicker
            $('.aiz-selectpicker').selectpicker();

            // Handle main category change
            $('#main_category').on('changed.bs.select', function(e, clickedIndex, isSelected, previousValue) {
                handleMainCategoryChange();
            });

            function handleMainCategoryChange() {
                var mainCategoryId = $('#main_category').val();
                var $additionalCategories = $('#additional_categories');
                var currentAdditional = $additionalCategories.val() || [];

                if (mainCategoryId) {
                    // Always ensure main category is in additional categories
                    if (!currentAdditional.includes(mainCategoryId)) {
                        currentAdditional.push(mainCategoryId);
                    }

                    // Set the values - don't disable the option so it submits
                    $additionalCategories.selectpicker('val', currentAdditional);

                    // Update hidden input
                    $('#main_category_included').val('1');
                } else {
                    // If no main category, clear additional categories
                    $additionalCategories.selectpicker('val', []);
                    $('#main_category_included').val('0');
                }

                $additionalCategories.selectpicker('refresh');
            }

            // Handle additional categories change
            $('#additional_categories').on('changed.bs.select', function(e, clickedIndex, isSelected,
                previousValue) {
                var mainCategoryId = $('#main_category').val();
                var selectedAdditional = $(this).val() || [];

                if (mainCategoryId) {
                    // If main category was unselected, re-add it
                    if (!selectedAdditional.includes(mainCategoryId)) {
                        selectedAdditional.push(mainCategoryId);
                        $(this).selectpicker('val', selectedAdditional);
                    }

                    $('#main_category_included').val('1');
                } else {
                    $('#main_category_included').val('0');
                }
            });

            // Single form submission handler
            $('form#choice_form').on('submit', function(e) {
                // Check if already attempted to prevent double submission
                if ($(".action-btn").attr('attempted') == 'true') {
                    e.preventDefault();
                    return false;
                }

                // Validate main category
                if (!$('#main_category').val()) {
                    e.preventDefault();
                    AIZ.plugins.notify('danger', '{{ translate('Please select a main category') }}');
                    return false;
                }

                // Ensure main category is in additional categories before submit
                var mainCategoryId = $('#main_category').val();
                var $additionalCategories = $('#additional_categories');
                var currentAdditional = $additionalCategories.val() || [];

                if (!currentAdditional.includes(mainCategoryId)) {
                    currentAdditional.push(mainCategoryId);
                    $additionalCategories.selectpicker('val', currentAdditional);
                    $additionalCategories.selectpicker('refresh');
                }

                // Mark as attempted
                $(".action-btn").attr("attempted", 'true');
            });

            // Initialize
            handleMainCategoryChange();

            $("[name=shipping_type]").on("change", function() {
                $(".flat_rate_shipping_div").hide();

                if ($(this).val() == 'flat_rate') {
                    $(".flat_rate_shipping_div").show();
                }
            });

            function add_more_customer_choice_option(i, name) {
                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    type: "POST",
                    url: '{{ route('products.add-more-choice-option') }}',
                    data: {
                        attribute_id: i
                    },
                    success: function(data) {
                        var obj = JSON.parse(data);
                        $('#customer_choice_options').append('\
                                <div class="col-12 col-md-4">\
                                    <div class="form-group">\
                                        <div class="col-md-12">\
                                            <input type="hidden" name="choice_no[]" value="' + i + '">\
                                            <label class="control-label fw-600 mb-2">' + name +
                            '</label>\
                                        </div>\
                                        <div class="col-md-12 px-0">\
                                            <select class="form-control aiz-selectpicker attribute_choice" data-live-search="true" name="choice_options_' +
                            i + '[]" data-selected-text-format="count" multiple>\
                                                ' + obj + '\
                                            </select>\
                                        </div>\
                                    </div>\
                                </div>');

                        // Initialize the selectpicker for the new element
                        $('.attribute_choice').selectpicker();
                        AIZ.plugins.bootstrapSelect('refresh');
                    }
                });
            }

            $('input[name="colors_active"]').on('change', function() {
                if (!$('input[name="colors_active"]').is(':checked')) {
                    $('#colors').prop('disabled', true);
                    AIZ.plugins.bootstrapSelect('refresh');
                } else {
                    $('#colors').prop('disabled', false);
                    AIZ.plugins.bootstrapSelect('refresh');
                }
                update_sku();
            });

            $(document).on("change", ".attribute_choice", function() {
                update_sku();
            });

            $('#colors').on('change', function() {
                update_sku();
            });

            $('input[name="unit_price"]').on('keyup', function() {
                update_sku();
            });

            $('input[name="name"]').on('keyup', function() {
                update_sku();
            });

            function delete_row(em) {
                $(em).closest('.form-group').remove();
                update_sku();
            }

            function delete_variant(em) {
                $(em).closest('.variant').remove();
            }

            function update_sku() {
                $.ajax({
                    type: "POST",
                    url: '{{ route('products.sku_combination') }}',
                    data: $('#choice_form').serialize(),
                    success: function(data) {
                        $('#sku_combination').html(data);
                        AIZ.uploader.previewGenerate();
                        AIZ.plugins.fooTable();
                        if (data.trim().length > 1) {
                            $('#show-hide-div').hide();
                        } else {
                            $('#show-hide-div').show();
                        }
                    }
                });
            }

            $('#choice_attributes').on('change', function() {
                $('#customer_choice_options').html(null);
                $.each($("#choice_attributes option:selected"), function() {
                    add_more_customer_choice_option($(this).val(), $(this).text());
                });

                update_sku();
            });
        });

        // Function to get all selected category IDs (if needed for other operations)
        function getAllSelectedCategories() {
            var mainCategory = $('#main_category').val();
            var additionalCategories = $('#additional_categories').val() || [];

            // Main category is always included in additional now
            return additionalCategories;
        }
    </script>

    @include('partials.product.product_temp_data')
@endsection
