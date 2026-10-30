<div class="pt-3 pt-lg-1 bg-white">
    <div class="">
          {{-- <h5 class="fs-14 fw-400 text-dark">{{ translate('Login to your account') }}</h5> --}}
        <form class="form-default" role="form" action="{{ route('login') }}" method="POST">
            @csrf

            {{-- Email or Phone --}}
            @if (addon_is_activated('otp_system'))
                <div class="form-group phone-form-group mb-1">
                    <label for="phone" class="fs-12 fw-700 text-soft-dark">{{ translate('Phone') }}</label>
                    <input type="tel" id="phone-code"
                        class="form-control{{ $errors->has('phone') ? ' is-invalid' : '' }} rounded-0"
                        value="{{ old('phone') }}" name="phone" autocomplete="off">
                </div>

                <input type="hidden" name="country_code" value="">

                <div class="form-group email-form-group mb-1 d-none">
                    <label for="email" class="fs-12 fw-700 text-soft-dark">{{ translate('Email') }}</label>
                    <input type="email"
                        class="form-control rounded-0 {{ $errors->has('email') ? ' is-invalid' : '' }}"
                        value="{{ old('email') }}" placeholder="{{ translate('johndoe@example.com') }}" name="email"
                        id="email" autocomplete="off">
                </div>

                <div class="form-group text-right">
                    <button class="btn btn-link p-0 text-primary" type="button"
                        onclick="toggleEmailPhone(this)"><i>*{{ translate('Use Email Instead') }}</i></button>
                </div>
            @else
                <div class="form-group">
                    <label for="email" class="fs-12 fw-700 text-soft-dark">{{ translate('Email') }}</label>
                    <input type="email"
                        class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }} rounded-0"
                        value="{{ old('email') }}" placeholder="{{ translate('johndoe@example.com') }}"
                        name="email" id="email" autocomplete="off">
                </div>
            @endif

            {{-- Password --}}
            <div class="form-group">
                <label for="password" class="fs-12 fw-700 text-soft-dark">{{ translate('Password') }}</label>
                <div class="position-relative">
                    <input type="password"
                        class="form-control rounded-0 {{ $errors->has('password') ? ' is-invalid' : '' }}"
                        placeholder="{{ translate('Password') }}" name="password" id="password">
                    <i class="password-toggle las la-2x la-eye"></i>
                </div>
            </div>

            <div class="row mb-2">
                {{-- Remember Me --}}
                <div class="col-6">
                    <label class="aiz-checkbox">
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                        <span
                            class="has-transition fs-12 fw-400 text-gray-dark hov-text-primary">{{ translate('Remember Me') }}</span>
                        <span class="aiz-square-check"></span>
                    </label>
                </div>
                {{-- Forgot password --}}
                <div class="col-6 text-right">
                    <a href="{{ route('password.request') }}"
                        class="text-reset fs-12 fw-400 text-gray-dark hov-text-primary"><u>{{ translate('Forgot password?') }}</u></a>
                </div>
            </div>

            {{-- Submit Button --}}
            <div class="mb-4 mt-4">
                <button type="submit"
                    class="btn btn-block bg-secondary-base text-white fw-700 fs-14 rounded-0 text-uppercase">{{ translate('Login In Now') }}</button>
            </div>
        </form>

        {{-- DEMO MODE --}}
        {{-- @if (env('DEMO_MODE') == 'On')
            <div class="mb-4">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr>
                            <td>{{ translate('Customer Account') }}</td>
                            <td>
                                <button class="btn btn-info btn-sm"
                                    onclick="autoFillCustomer()">{{ translate('Copy credentials') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif --}}

        <p class="text-center">If You are an existing customer,please <strong>Log in</strong> with your account with the same mobile number or email address that used previously</p>

        {{-- Social Login --}}
        {{-- @if (get_setting('google_login') == 1 ||
                get_setting('facebook_login') == 1 ||
                get_setting('twitter_login') == 1 ||
                get_setting('apple_login') == 1)
            <div class="text-center mb-3">
                <span class="bg-white fs-12 text-gray">{{ translate('Or Login With') }}</span>
            </div>
            <ul class="list-inline social colored text-center mb-4">
                @if (get_setting('facebook_login') == 1)
                    <li class="list-inline-item">
                        <a href="{{ route('social.login', ['provider' => 'facebook']) }}" class="facebook">
                            <i class="lab la-facebook-f"></i>
                        </a>
                    </li>
                @endif
                @if (get_setting('google_login') == 1)
                    <li class="list-inline-item">
                        <a href="{{ route('social.login', ['provider' => 'google']) }}" class="google">
                            <i class="lab la-google"></i>
                        </a>
                    </li>
                @endif
                @if (get_setting('twitter_login') == 1)
                    <li class="list-inline-item">
                        <a href="{{ route('social.login', ['provider' => 'twitter']) }}" class="twitter">
                            <i class="lab la-twitter"></i>
                        </a>
                    </li>
                @endif
                @if (get_setting('apple_login') == 1)
                    <li class="list-inline-item">
                        <a href="{{ route('social.login', ['provider' => 'apple']) }}" class="apple">
                            <i class="lab la-apple"></i>
                        </a>
                    </li>
                @endif
            </ul>
        @endif --}}

        {{-- Social Login --}}
@if (get_setting('google_login') == 1 ||
        get_setting('facebook_login') == 1 ||
        get_setting('twitter_login') == 1 ||
        get_setting('apple_login') == 1)
    <div class="text-center mb-3">
        <span class="bg-white fs-12 text-gray">{{ translate('Or Login With') }}</span>
    </div>

    <div class="d-grid gap-2 mb-3">
        @if (get_setting('google_login') == 1)
            <a href="{{ route('social.login', ['provider' => 'google']) }}" class="btn btn-dark d-flex align-items-center justify-content-center w-100 mb-2 rounded-0">
                <i class="lab la-google fs-18 me-2"></i>
                <span class="flex-grow-1 text-center text-uppercase">{{ translate('Log in with Google') }}</span>
            </a>
        @endif

        @if (get_setting('facebook_login') == 1)
            <a href="{{ route('social.login', ['provider' => 'facebook']) }}" class="btn btn-dark d-flex align-items-center justify-content-center w-100 mb-2 rounded-0">
                <i class="lab la-facebook-f fs-18 me-2"></i>
                <span class="flex-grow-1 text-center text-uppercase">{{ translate('Log in with Facebook') }}</span>
            </a>
        @endif

        @if (get_setting('twitter_login') == 1)
            <a href="{{ route('social.login', ['provider' => 'twitter']) }}" class="btn btn-dark d-flex align-items-center justify-content-center w-100 mb-2 rounded-0">
                <i class="lab la-twitter fs-18 me-2"></i>
                <span class="flex-grow-1 text-center text-uppercase">{{ translate('Log in with Twitter') }}</span>
            </a>
        @endif

        @if (get_setting('apple_login') == 1)
            <a href="{{ route('social.login', ['provider' => 'apple']) }}" class="btn btn-dark d-flex align-items-center justify-content-center w-100 mb-2 rounded-0">
                <i class="lab la-apple fs-18 me-2"></i>
                <span class="flex-grow-1 text-center text-uppercase">{{ translate('Log in with Apple') }}</span>
            </a>
        @endif
    </div>
@endif


        {{-- Register Now --}}
        {{-- <p class="fs-12 text-gray mb-0">
            {{ translate('Dont have an account?') }}
            <a href="#" class="ml-2 fs-14 fw-700 animate-underline-primary" data-toggle="modal"
                data-target="#signupModal" data-dismiss="modal">
                {{ translate('Register Now') }}
            </a>
        </p> --}}
    </div>
</div>

<script type="text/javascript">
    function autoFillCustomer() {
        $('#email').val('customer@example.com');
        $('#password').val('123456');
    }
</script>
