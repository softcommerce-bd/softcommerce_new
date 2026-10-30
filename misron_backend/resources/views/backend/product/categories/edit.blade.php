@extends('backend.layouts.app')

@section('content')

@php
    CoreComponentRepository::instantiateShopRepository();
    CoreComponentRepository::initializeCache();
@endphp

<div class="aiz-titlebar text-left mt-2 mb-3">
    <h5 class="mb-0 h6">{{translate('Category Information')}}</h5>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-fill language-bar">
                    @foreach (get_all_active_language() as $key => $language)
                    <li class="nav-item">
                        <a class="nav-link text-reset @if ($language->code == $lang) active @endif py-3" href="{{ route('categories.edit', ['id'=>$category->id, 'lang'=> $language->code] ) }}">
                            <img src="{{ static_asset('assets/img/flags/'.$language->code.'.png') }}" height="11" class="mr-1">
                            <span>{{$language->name}}</span>
                        </a>
                    </li>
                    @endforeach
                </ul>
                <form class="p-4" action="{{ route('categories.update', $category->id) }}" method="POST" enctype="multipart/form-data">
                    <input name="_method" type="hidden" value="PATCH">
    	            <input type="hidden" name="lang" value="{{ $lang }}">
                	@csrf
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{translate('Name')}} <i class="las la-language text-danger" title="{{translate('Translatable')}}"></i></label>
                        <div class="col-md-9">
                            <input type="text" name="name" value="{{ $category->getTranslation('name', $lang) }}" class="form-control" id="name" placeholder="{{translate('Name')}}" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{translate('Type')}}</label>
                        <div class="col-md-9">
                            <select name="digital" onchange="categoriesByType(this.value)" required class="form-control aiz-selectpicker mb-2 mb-md-0">
                                <option value="0" @if ($category->digital == '0') selected @endif>{{translate('Physical')}}</option>
                                <option value="1" @if ($category->digital == '1') selected @endif>{{translate('Digital')}}</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{translate('Parent Category')}}</label>
                        <div class="col-md-9">
                            <select class="select2 form-control aiz-selectpicker" name="parent_id" data-toggle="select2" data-placeholder="Choose ..."data-live-search="true" data-selected="{{ $category->parent_id }}">
                                @include('backend.product.categories.categories_option', ['categories' => $categories]){{-- <option value="0">{{ translate('No Parent') }}</option>
                                @foreach ($categories as $acategory)
                                    <option value="{{ $acategory->id }}">{{ $acategory->getTranslation('name') }}</option>
                                    @foreach ($acategory->childrenCategories as $childCategory)
                                        @include('categories.child_category', ['child_category' => $childCategory])
                                    @endforeach
                                @endforeach --}}
                            </select>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">
                            {{translate('Ordering Number')}}
                        </label>
                        <div class="col-md-9">
                            <input type="number" name="order_level" value="{{ $category->order_level }}" class="form-control" id="order_level" placeholder="{{translate('Order Level')}}">
                            <small>{{translate('Higher number has high priority')}}</small>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">
                            {{translate('Sub Ordering Number')}}
                        </label>
                        <div class="col-md-9">
                            <input type="number" name="sub_order_level" value="{{ $category->sub_order_level }}" class="form-control" id="sub_order_level" placeholder="{{translate('Sub Order Level')}}">
                            <small>{{translate('Higher number has high priority')}}</small>
                        </div>
                    </div>
                     {{--<div class="form-group row">
                        <label class="col-md-3 col-form-label" for="signinSrEmail">{{translate('Header Banner')}} <small>({{ translate('1920x150') }})</small></label>
                        <div class="col-md-9">
                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                <div class="input-group-prepend">
                                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                </div>
                                <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                <input type="hidden" name="header_banner" class="selected-files" value="{{ $category->header_banner }}">
                            </div>
                            <div class="file-preview box sm">
                            </div>
                        </div>
                    </div>--}}
    	            <div class="form-group row">
                        <label class="col-md-3 col-form-label" for="signinSrEmail">{{translate('Banner')}} <small>({{ translate('200x200') }})</small></label>
                        <div class="col-md-9">
                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                <div class="input-group-prepend">
                                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                </div>
                                <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                <input type="hidden" name="banner" class="selected-files" value="{{ $category->banner }}">
                            </div>
                            <div class="file-preview box sm">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label" for="signinSrEmail">{{translate('Icon')}} <small>({{ translate('32x32') }})</small></label>
                        <div class="col-md-9">
                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                <div class="input-group-prepend">
                                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                </div>
                                <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                <input type="hidden" name="icon" class="selected-files" value="{{ $category->icon }}">
                            </div>
                            <div class="file-preview box sm">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label" for="signinSrEmail">{{translate('Cover Image')}} <small>({{ translate('360x360') }})</small></label>
                        <div class="col-md-9">
                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                <div class="input-group-prepend">
                                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse')}}</div>
                                </div>
                                <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                <input type="hidden" name="cover_image" class="selected-files" value="{{ $category->cover_image }}">
                            </div>
                            <div class="file-preview box sm">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{translate('Meta Title')}}</label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="meta_title" value="{{ $category->meta_title }}" placeholder="{{translate('Meta Title')}}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{translate('Meta Description')}}</label>
                        <div class="col-md-9">
                            <textarea name="meta_description" rows="5" class="form-control">{{ $category->meta_description }}</textarea>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{translate('Slug')}}</label>
                        <div class="col-md-9">
                            <input type="text" placeholder="{{translate('Slug')}}" id="slug" name="slug" value="{{ $category->slug }}" class="form-control">
                        </div>
                    </div>
                    @if (get_setting('category_wise_commission') == 1)
                        <div class="form-group row">
                            <label class="col-md-3 col-form-label">{{translate('Commission Rate')}}</label>
                            <div class="col-md-9 input-group">
                                <input type="number" lang="en" min="0" step="0.01" id="commision_rate" name="commision_rate" value="{{ $category->commision_rate }}" class="form-control">
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    @endif
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{translate('Filtering Attributes')}}</label>
                        <div class="col-md-9">
                            <select class="select2 form-control aiz-selectpicker" name="filtering_attributes[]" data-toggle="select2" data-placeholder="Choose ..."data-live-search="true" data-selected="{{ $category->attributes->pluck('id') }}" multiple>
                                @foreach (\App\Models\Attribute::all() as $attribute)
                                    <option value="{{ $attribute->id }}">{{ $attribute->getTranslation('name') }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                     <!-- Header Banner Part -->
                        <div class="header_banner_target">
                            <h4 class="text-center">Header Banner Images & Links</h4>
                        
                            @if (!empty($category->header_banner_images))
                                @foreach ($category->header_banner_images as $index => $image)
                                    <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
                                        <div class="row gutters-5">
                                            <!-- Image -->
                                            <div class="col-md-5">
                                                <div class="form-group mb-md-0">
                                                    <div class="input-group" data-toggle="aizuploader" data-type="image">
                                                        <div class="input-group-prepend">
                                                            <div class="input-group-text bg-soft-secondary font-weight-medium">Browse</div>
                                                        </div>
                                                        <div class="form-control file-amount">Choose File</div>
                                                        <input type="hidden" name="header_banner_images[]" class="selected-files" value="{{ $image }}">
                                                    </div>
                                                    <div class="file-preview box sm">
                                                        <img src="{{ asset('uploads/' . $image) }}" width="50" alt="Banner">
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Link -->
                                            <div class="col-md">
                                                <div class="form-group mb-md-0">
                                                    <input type="text" class="form-control" placeholder="http://" name="header_banner_links[]" value="{{ $category->header_banner_links[$index] ?? '' }}">
                                                </div>
                                            </div>
                                            <!-- Remove Button -->
                                            <div class="col-md-auto">
                                                <div class="form-group mb-md-0">
                                                    <button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger remove-row">
                                                        <i class="las la-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                        
                        <!-- Add New Row Button -->
                        <div class="">
                            <button type="button" class="btn btn-block border hov-bg-soft-secondary fs-14 rounded-0 d-flex align-items-center justify-content-center add-more-btn mb-4" style="background: #fcfcfc;">
                                <i class="las la-2x text-success la-plus-circle"></i>
                                <span class="ml-2">Add New</span>
                            </button>
                        </div>
                    <div class="form-group mb-0 text-right">
                        <button type="submit" class="btn btn-primary">{{translate('Save')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')

<script type="text/javascript">
    function categoriesByType(val){
        $('select[name="parent_id"]').html('');
        AIZ.plugins.bootstrapSelect('refresh');
        $.ajax({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            type:"POST",
            url:'{{ route('categories.categories-by-type') }}',
            data:{
               digital: val
            },
            success: function(data) {
                $('select[name="parent_id"]').html(data);
                AIZ.plugins.bootstrapSelect('refresh');
            }
        });
    }
    
     $(document).ready(function() {
    // Click event for "Add New" button
    $(".add-more-btn").click(function() {
        var newRow = `
        <div class="p-3 p-md-4 mb-3 mb-md-2rem remove-parent" style="border: 1px dashed #e4e5eb;">
            <div class="row gutters-5">
                <!-- Image -->
                <div class="col-md-5">
                    <div class="form-group mb-md-0">
                        <div class="input-group" data-toggle="aizuploader" data-type="image">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">Browse</div>
                            </div>
                            <div class="form-control file-amount">Choose File</div>
                            <input type="hidden" name="header_banner_images[]" class="selected-files" value="">
                        </div>
                        <div class="file-preview box sm"></div>
                    </div>
                </div>
                <!-- Link -->
                <div class="col-md">
                    <div class="form-group mb-md-0">
                        <input type="text" class="form-control" placeholder="http://" name="header_banner_links[]" value="">
                    </div>
                </div>
                <!-- Remove Button -->
                <div class="col-md-auto">
                    <div class="form-group mb-md-0">
                        <button type="button" class="mt-1 btn btn-icon btn-circle btn-sm btn-soft-danger remove-row">
                            <i class="las la-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        $(".header_banner_target").append(newRow); // Append new row
    });

    // Click event for remove row button (Dynamic event binding)
    $(document).on("click", ".remove-row", function() {
        $(this).closest(".remove-parent").remove();
    });
});
</script>

@endsection
