@extends('backend.layouts.app')

@section('content')

<div class="page-content" style="background-color: white !important;">
    <div class="row d-flex justify-content-center pt-5"> <!-- Center the form -->
        <div class="col-md-4 border p-4 rounded">
            <form action="{{route('bd-courier.create-or-update')}}" method="POST">
                @csrf
                <h4>BD Courier Credential</h4>
                <div class="form-group">
                    <label for="steadfast_api_key">BD Courier Key</label>
                    <input type="text" class="form-control" id="token" name="token" value="{{ $setting->token ?? '' }}" required>
                </div>
            
                <button type="submit" class="btn btn-primary">Save Token</button>
            </form>
        </div>
    </div>
</div>
 
@endsection