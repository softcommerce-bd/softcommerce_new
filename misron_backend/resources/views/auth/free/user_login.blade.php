@extends('frontend.layouts.app')

@section('content')
<div class="">
<style>
    .password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #666;
}
   .active-tab {
        font-weight: bold;
        color: #000;
        border-bottom: 2px solid #000;
    }
    .tab-sec h4 {
        cursor: pointer;
        padding-bottom: 5px;
        margin: 0;
    }
</style>
    {{-- Site Icon & Title --}}
    <div class="header-name-sec mx-auto pt-4">
        <a href="{{ route('home') }}">
            <div class="size-40px mb-1 mx-auto border border-dark rounded">
            <img src="{{ uploaded_asset(get_setting('site_icon')) }}" alt="{{ translate('Site Icon') }}" class="img-fit h-100">
        </div>
        <div class="text-center">
            <h1 class="fs-16 fs-md-24 fw-700 text-dark">{{ translate('My') }} {{ get_setting('site_name') }}</h1>
        </div>
        </a>
    </div>

    {{-- Tab Section --}}
    <div class="tab-sec text-center mt-3">
        <div class="row">
            <div class="col-md-4 mx-auto d-flex">
                <h4 class="flex-fill text-uppercase fs-14 active-tab" id="loginTab">{{ translate('Log in') }}</h4>
                <h4 class="flex-fill text-uppercase fs-14" id="registerTab">{{ translate('Register') }}</h4>
            </div>
        </div>
    </div>


    {{-- Login Form --}}
    <section id="loginSection" class="bg-white">
        <div class="row">
            <div class="col-xxl-6 col-lg-5 mx-auto mb-4">
                <div class="col-xxl-8 p-4 p-lg-5 mx-auto shadow-lg rounded">
                    @include('auth.free.partials.user_login_form')
                </div>
            </div>
        </div>
    </section>

    {{-- Register Form (Hidden initially) --}}
    <section id="registerSection" class="bg-white d-none">
        <div class="row">
            <div class="col-xxl-6 col-lg-5 mx-auto mb-4">
                <div class="col-xxl-8 p-4 p-lg-5 mx-auto shadow-lg rounded">
                    {{-- <div class="text-center mb-3">
                        <h1 class="fs-20 fs-md-24 fw-700 text-primary text-uppercase">
                            {{ translate('Create an account') }}
                        </h1>
                    </div> --}}
                    @include('auth.free.partials.user_register_form')
                </div>
            </div>
        </div>
    </section>

</div>
@endsection


<script>
document.addEventListener("DOMContentLoaded", function () {
    const loginTab = document.getElementById('loginTab');
    const registerTab = document.getElementById('registerTab');
    const loginSection = document.getElementById('loginSection');
    const registerSection = document.getElementById('registerSection');


    if (loginTab && registerTab) {
        loginTab.addEventListener('click', () => {
            loginSection.classList.remove('d-none');
            registerSection.classList.add('d-none');
            loginTab.classList.add('active-tab');
            registerTab.classList.remove('active-tab');
        });

        registerTab.addEventListener('click', () => {
            loginSection.classList.add('d-none');
            registerSection.classList.remove('d-none');
            registerTab.classList.add('active-tab');
            loginTab.classList.remove('active-tab');
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const toggles = document.querySelectorAll(".password-toggle");

    toggles.forEach(toggle => {
        toggle.addEventListener("click", function () {
            const input = this.previousElementSibling;

            if (input.type === "password") {
                input.type = "text";
                this.classList.remove("la-eye");
                this.classList.add("la-eye-slash");
            } else {
                input.type = "password";
                this.classList.remove("la-eye-slash");
                this.classList.add("la-eye");
            }
        });
    });
});

</script>
@section('script')

@include('auth.login_register_js')

@endsection

