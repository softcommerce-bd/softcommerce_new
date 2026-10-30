<?php

namespace App\Http\Controllers\Auth;

use Nexmo;
use Cookie;
use Session;
use App\Models\Cart;
use App\Models\User;
use Twilio\Rest\Client;

use App\Rules\Recaptcha;
use Illuminate\Validation\Rule;

use App\Models\Customer;
use App\OtpConfiguration;
use Illuminate\Http\Request;
use App\Models\BusinessSetting;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use App\Http\Controllers\OTPVerificationController;
use App\Notifications\EmailVerificationNotification;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
            'g-recaptcha-response' => [
                Rule::when(get_setting('google_recaptcha') == 1, ['required', new Recaptcha()], ['sometimes'])
            ]
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    // protected function create(array $data)
    // {
    //     if (filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    //         $user = User::create([
    //             'name' => $data['name'],
    //             'email' => $data['email'],
    //             'password' => Hash::make($data['password']),
    //         ]);
    //     }
    //     else {
    //         if (addon_is_activated('otp_system')){
    //             $user = User::create([
    //                 'name' => $data['name'],
    //                 'phone' => '+'.$data['country_code'].$data['phone'],
    //                 'password' => Hash::make($data['password']),
    //                 'verification_code' => rand(100000, 999999)
    //             ]);

    //             $otpController = new OTPVerificationController;
    //             $otpController->send_code($user);
    //         }
    //     }
        
    //     if(session('temp_user_id') != null){
    //         if(auth()->user()->user_type == 'customer'){
    //             Cart::where('temp_user_id', session('temp_user_id'))
    //             ->update(
    //                 [
    //                     'user_id' => auth()->user()->id,
    //                     'temp_user_id' => null
    //                 ]
    //             );
    //         }
    //         else {
    //             Cart::where('temp_user_id', session('temp_user_id'))->delete();
    //         }
    //         Session::forget('temp_user_id');
    //     }

    //     if(Cookie::has('referral_code')){
    //         $referral_code = Cookie::get('referral_code');
    //         $referred_by_user = User::where('referral_code', $referral_code)->first();
    //         if($referred_by_user != null){
    //             $user->referred_by = $referred_by_user->id;
    //             $user->save();
    //         }
    //     }

    //     return $user;
    // }
    
    protected function create(array $data)
{
    if (filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
    } else {
        // if (addon_is_activated('otp_system')) {
        //     $otp_code = rand(100000, 999999); // Generate a 6-digit OTP
        //     $user = User::create([
        //         'name' => $data['name'],
        //         'phone' => '+' . $data['country_code'] . $data['phone'],
        //         'password' => Hash::make($data['password']),
        //         'verification_code' => $otp_code,
        //     ]);

        //     // Send OTP via SMS
        //     $this->sendOtpSms($user->phone, $otp_code);
        // }
        // else{
        // extra added for phone otp
           $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
        ]); 
        // }
    }

    return $user;
}

/**
 * Send OTP to user phone number.
 */
protected function sendOtpSms($phone, $otp_code)
{
    // dd($phone,$otp_code);
    // $url = 'https://www.24bulksmsbd.com/api/smsSendApi';
    $url = 'https://amarsmsbd.xyz/api/smsSendApi';
    $message = "Your verification code is: $otp_code";

    $data = array(
        'customer_id' => 943,
        'api_key' => '738f7d77f30a58e981801827a633b94caaad7cc200e00',
        'message' => $message,
        'mobile_no' => $phone
    );

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);     
    $output = curl_exec($curl);
    curl_close($curl);

    // Optionally, log or check the output for debugging.
    return $output;
}

    public function register(Request $request)
    {
        // dd($request->all());
        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            if(User::where('email', $request->email)->first() != null){
                flash(translate('Email or Phone already exists.'));
                return back();
            }
        }
        elseif (User::where('phone', '+'.$request->country_code.$request->phone)->first() != null) {
            flash(translate('Phone already exists.'));
            return back();
        }

        $this->validator($request->all())->validate();

        $user = $this->create($request->all());
//   dd($user);
        $this->guard()->login($user);

        if($user->email != null){
            if(BusinessSetting::where('type', 'email_verification')->first()->value != 1){
                $user->email_verified_at = date('Y-m-d H:m:s');
                $user->save();
                offerUserWelcomeCoupon();
                flash(translate('Registration successful.'))->success();
            }
            else {
                try {
                    $user->sendEmailVerificationNotification();
                    flash(translate('Registration successful. Please verify your email.'))->success();
                } catch (\Throwable $th) {
                    $user->delete();
                    flash(translate('Registration failed. Please try again later.'))->error();
                }
            }
        }
        // extra added without otp message for phone
           elseif (!empty($user->phone)) {
               $user->email_verified_at = date('Y-m-d H:m:s');
                $user->save();
        flash(translate('Registration successful.'))->success();
        return redirect()->route('home');
    }


            return $this->registered($request, $user) ?: redirect($this->redirectPath());
    }
    


    protected function registered(Request $request, $user)
    {
        // dd($request->all(),$user);
        if ($user->email == null) {
            // dd('hello');
            return redirect()->route('verification');
        }elseif(session('link') != null){
            return redirect(session('link'));
        }else {
            return redirect()->route('home');
        }
    }
}
