@extends('frontend.layouts.app')

@section('content')
    <section class="text-center py-6">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mx-auto">
                    {{-- <img src="{{ static_asset('assets/img/404.svg') }}" class="mw-100 mx-auto mb-5" height="300"> --}}
                    <h1 style="font-size: 80px; font-weight:800;">404</h1>
                    <div class="position-relative">
                        <h1 class="fw-700 position-relative fs-30" style="z-index: 2;">
                            {{ translate('oops! Page Not Found') }}
                        </h1>
                        <hr style="height: 20px; width:100%; position: absolute; top:8px; z-index: 1;" class="bg-primary">
                    </div>
                    <p class="fs-16 opacity-60 pt-3">
                        {{ translate("Sorry! The page you're looking for clocked out!") }}
					</p>
                </div>
				
            </div>
			<div class="row">
				<div class="col-md-4 mx-auto">
					<div class="btn" style="text-decoration: underline;"><a href="{{ route('home') }}" class="text-dark fs-16 fw-600">Return to shop</a></div>
				</div>
			</div>
        </div>
    </section>
@endsection
