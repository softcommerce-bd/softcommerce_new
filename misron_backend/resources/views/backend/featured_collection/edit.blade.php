@extends('backend.layouts.app')

@section('content')

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 h6">{{ translate('Edit Featured Collection') }}</h5>
            </div>
            <div class="card-body">
                <form class="form-horizontal" action="{{ route('featured_collection.update', $featured_collection->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    {{-- Title --}}
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ translate('Title') }}</label>
                        <div class="col-md-9">
                            <input type="text" 
                                   placeholder="{{ translate('Title') }}" 
                                   id="title" 
                                   name="title" 
                                   class="form-control" 
                                   value="{{ old('title', $featured_collection->title) }}" 
                                   required>
                        </div>
                    </div>

                    {{-- Link --}}
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label">{{ translate('Link') }}</label>
                        <div class="col-md-9">
                            <input type="text" 
                                   placeholder="{{ translate('Link') }}" 
                                   id="link" 
                                   name="link" 
                                   class="form-control" 
                                   value="{{ old('link', $featured_collection->link) }}" 
                                   required>
                        </div>
                    </div>

                    {{-- Image --}}
                    <div class="form-group row">
                        <label class="col-md-3 col-form-label" for="signinSrEmail">
                            {{ translate('Image') }} <small>({{ translate('200x200') }})</small>
                        </label>
                        <div class="col-md-9">
                            <div class="input-group" data-toggle="aizuploader" data-type="image">
                                <div class="input-group-prepend">
                                    <div class="input-group-text bg-soft-secondary font-weight-medium">{{ translate('Browse') }}</div>
                                </div>
                                <div class="form-control file-amount">{{ translate('Choose File') }}</div>
                                <input type="hidden" name="image" class="selected-files" value="{{ $featured_collection->image }}">
                            </div>
                            <div class="file-preview box sm">
                            </div>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="form-group mb-0 text-right">
                        <button type="submit" class="btn btn-primary">{{ translate('Update') }}</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

@endsection
