@extends('backend.layouts.app')

@section('content')
    <div class="page-content" style="background-color: white !important;">
        <div class="row d-flex justify-content-center pt-5">
            <div class="col-md-4 border p-4 rounded">
                <form action="{{ route('settings.update.pathao') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <h4>Pathao Courier Credential</h4>
                    <div class="form-group">
                        <label for="base_url">Base Url <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="base_url" name="base_url"
                            value="{{ $settings->base_url ?? '' }}" required>
                    </div>

                    <div class="form-group">
                        <label for="store_id">Store Id</label>
                        <input type="text" class="form-control" id="store_id" name="store_id"
                            value="{{ $settings->store_id ?? '' }}">
                    </div>
                    <div class="form-group">
                        <label for="client_id">Client Id <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="client_id" name="client_id"
                            value="{{ $settings->client_id ?? '' }}" required>
                    </div>
                    <div class="form-group">
                        <label for="client_secret">Client Secret<span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="client_secret" name="client_secret"
                            value="{{ $settings->client_secret ?? '' }}" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username"
                            value="{{ $settings->username ?? '' }}" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="password" name="password"
                            value="{{ $settings->password ?? '' }}" required>
                    </div>
                    
                      <!-- Sandbox Switch -->
                    <div class="form-group form-check">
                       <input type="checkbox" class="form-check-input" id="sandbox" name="sandbox"
                            {{ isset($settings->sandbox) && $settings->sandbox ? 'checked' : '' }}>
                        <label class="form-check-label" for="sandbox">Sandbox Mode</label>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Settings</button>
                </form>
            </div>
        </div>
    </div>
@endsection
