@extends('backend.layouts.app')

@section('content')

<div class="page-content" style="background-color: white !important;">
    <div class="row d-flex justify-content-center pt-5"> <!-- Center the form -->
        <div class="col-md-4 border p-4 rounded">
            <form action="{{ route('settings.update.api.keys') }}" method="POST">
                @csrf
                @method('PUT')
                <h4>Steadfast Courier Credential</h4>
                <div class="form-group">
                    <label for="steadfast_api_key">Steadfast API Key</label>
                    <input type="text" class="form-control" id="steadfast_api_key" name="steadfast_api_key" value="{{ $settings->steadfast_api_key ?? '' }}" required>
                </div>
            
                <div class="form-group">
                    <label for="steadfast_secret_key">Steadfast Secret Key</label>
                    <input type="text" class="form-control" id="steadfast_secret_key" name="steadfast_secret_key" value="{{ $settings->steadfast_secret_key ?? '' }}" required>
                </div>
            
                <button type="submit" class="btn btn-primary">Save API Keys</button>
            </form>
        </div>
    </div>
</div>

@endsection
