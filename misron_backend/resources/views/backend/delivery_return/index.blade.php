@extends('backend.layouts.app')

@section('content')
    <!-- Delivery Return form -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow-none bg-light">
                <div class="card-header">
                    <h6 class="mb-0">{{ translate('Delivery Return') }}</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('business_settings.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <!-- About description -->
                        <div class="form-group">
                            <label>{{ translate('Footer description') }} ({{ translate('Translatable') }})</label>
                            <input type="hidden" name="types[]" value="delivery_return_modal">
                            {{-- <textarea class="form-control" name="delivery_return_modal" rows="6" placeholder="Type..">{{ get_setting('delivery_return_modal') }}</textarea> --}}
                            <textarea class="aiz-text-editor form-control" name="delivery_return_modal"
                                data-buttons='[["font", ["bold", "underline", "italic"]],["para", ["ul", "ol"]],["view", ["undo","redo"]]]'
                                placeholder="Type.." data-min-height="150">
                                        {!! get_setting('delivery_return_modal') !!}
                                    </textarea>

                        </div>
                        <!-- Update Button -->
                        <div class="mt-4 text-right">
                            <button type="submit"
                                class="btn btn-success w-230px btn-md rounded-2 fs-14 fw-700 shadow-success">{{ translate('Update') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
