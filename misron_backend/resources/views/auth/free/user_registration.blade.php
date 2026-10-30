@extends('auth.layouts.authentication')

@section('content')
   <!-- aiz-main-wrapper -->
   <div class="">
        <section class="bg-white overflow-hidden" style="min-height:100vh;">
            <div class="row" style="min-height: 100vh;">
                <!-- Left Side Image-->
               
                
                <!-- Right Side -->
                <div class="col-xxl-6 col-lg-5">
                    <div class="right-content">
                        <div class="row align-items-center justify-content-center justify-content-lg-start h-100">
                            <div class="col-xxl-6 p-4 p-lg-5">
                                <!-- Site Icon -->
                                <div class="size-71px mb-3 mx-auto mx-lg-0">
                                    <img src="{{ uploaded_asset(get_setting('site_icon')) }}" alt="{{ translate('Site Icon')}}" class="img-fit h-100">
                                </div>
                                <!-- Titles -->
                                <div class="text-center text-lg-left">
                                    <h1 class="fs-20 fs-md-24 fw-700 text-primary" style="text-transform: uppercase;">{{ translate('Create an account')}}</h1>
                                </div>
                                <!-- Register form -->
                                 {{-- Include Partial --}}
                            @include('auth.free.partials.user_register_form')
                               
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

