<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AffiliateOption;
use App\Models\AffiliateConfig;
use App\Models\AffiliateUser;
use App\Models\AffiliatePayment;
use App\Models\AffiliateWithdrawRequest;
use App\Models\AffiliateLog;
use App\Models\AffiliateStats;
use App\Models\User;
use Carbon\Carbon;
use Auth;
use DB;
use Hash;
use Log;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    /**
     * Get affiliate system information
     */
    public function affiliateInfo()
    {
        try {
            $affiliate_options = AffiliateOption::where('status', 1)->get();

            $verification_form = AffiliateConfig::where('type', 'verification_form')->first();
            $verification_form = $verification_form ? json_decode($verification_form->value) : [];

            $minimum_withdraw = AffiliateConfig::where('type', 'minimum_affiliate_withdraw_amount')->first();
            $validation_time = AffiliateConfig::where('type', 'validation_time')->first();

            return response()->json([
                'result' => true,
                'data' => [
                    'affiliate_options' => $affiliate_options,
                    'verification_form' => $verification_form,
                    'minimum_withdraw_amount' => $minimum_withdraw ? $minimum_withdraw->value : 0,
                    'validation_time' => $validation_time ? $validation_time->value : 0,
                    'is_affiliate_active' => AffiliateOption::where('status', 1)->exists()
                ]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply for affiliate program
     */
    public function applyForAffiliate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required_if:is_guest,true',
            'email' => 'required_if:is_guest,true|email|unique:users,email',
            'password' => 'required_if:is_guest,true|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->all()
            ], 400);
        }

        try {
            // Handle guest registration
            if (!Auth::check()) {
                $user = new User;
                $user->name = $request->name;
                $user->email = $request->email;
                $user->user_type = "customer";
                $user->password = Hash::make($request->password);
                // DO NOT generate referral_code here for regular users
                $user->save();

                auth()->login($user, false);

                if (get_setting('email_verification') != 1) {
                    $user->email_verified_at = date('Y-m-d H:m:s');
                    $user->save();
                } else {
                    event(new Registered($user));
                }
            }

            $user = Auth::user();
            // DEBUG: Log the user info
            Log::info('Affiliate Application - User:', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'is_authenticated' => Auth::check()
            ]);

            // Check if already an affiliate
            $existing_affiliate = AffiliateUser::where('user_id', $user->id)->first();
            Log::info('Affiliate Application - Existing:', [
                'found' => $existing_affiliate ? 'yes' : 'no',
                'status' => $existing_affiliate ? $existing_affiliate->status : 'N/A'
            ]);
            if ($existing_affiliate && $existing_affiliate->status == 1) {
                return response()->json([
                    'result' => false,
                    'message' => translate('You are already an affiliate user!')
                ], 400);
            }

            // *** GENERATE REFERRAL CODE ONLY WHEN BECOMING AN AFFILIATE ***
            if (empty($user->referral_code)) {
                $user->referral_code = $this->generateUniqueReferralCode();

                // Handle referral - if this affiliate was referred by someone
                if ($request->has('referral_code') && $request->referral_code) {
                    $referred_by_user = User::where('referral_code', $request->referral_code)->first();
                    if ($referred_by_user && $referred_by_user->id != $user->id) {
                        $user->referred_by = $referred_by_user->id;
                    }
                }

                $user->save();
            }

            // Create or update affiliate user
            $affiliate_user = $existing_affiliate ?? new AffiliateUser;
            $affiliate_user->user_id = $user->id;

            // Process verification form data
            $data = [];
            $verification_config = AffiliateConfig::where('type', 'verification_form')->first();

            if ($verification_config) {
                $verification_fields = json_decode($verification_config->value);
                $i = 0;
                foreach ($verification_fields as $key => $element) {
                    $item = [];
                    if ($element->type == 'text') {
                        $item['type'] = 'text';
                        $item['label'] = $element->label;
                        $item['value'] = $request['element_' . $i];
                    } elseif ($element->type == 'select' || $element->type == 'radio') {
                        $item['type'] = 'select';
                        $item['label'] = $element->label;
                        $item['value'] = $request['element_' . $i];
                    } elseif ($element->type == 'multi_select') {
                        $item['type'] = 'multi_select';
                        $item['label'] = $element->label;
                        $item['value'] = json_encode($request['element_' . $i]);
                    } elseif ($element->type == 'file') {
                        if ($request->hasFile('element_' . $i)) {
                            $item['type'] = 'file';
                            $item['label'] = $element->label;
                            $item['value'] = $request['element_' . $i]->store('uploads/affiliate_verification_form');
                        }
                    }
                    if (!empty($item)) {
                        array_push($data, $item);
                    }
                    $i++;
                }
            }

            $affiliate_user->informations = json_encode($data);
            $affiliate_user->save();

            return response()->json([
                'result' => true,
                'message' => translate('Your verification request has been submitted successfully!'),
                'data' => [
                    'affiliate_user' => $affiliate_user,
                    'referral_code' => $user->referral_code
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a unique referral code
     */
    private function generateUniqueReferralCode()
    {
        do {
            $code = Str::random(10);
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Get affiliate dashboard
     */
    public function dashboard()
    {
        try {
            $user = Auth::user();
            $affiliate_user = $user->affiliate_user; // Using relationship

            if (!$affiliate_user) {
                return response()->json([
                    'result' => false,
                    'message' => translate('You are not an affiliate user')
                ], 404);
            }

            return response()->json([
                'result' => true,
                'data' => [
                    'affiliate_user_id' => $affiliate_user->id, // Add this for debugging
                    'balance' => $affiliate_user->balance ?? 0,
                    'referral_code' => $user->referral_code,
                    'status' => $affiliate_user->status
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get affiliate statistics
     */
    public function affiliateStats(Request $request)
    {
        try {
            $user = Auth::user();
            $type = $request->type ?? '';

            // Get the affiliate_user record first
            $affiliate_user = AffiliateUser::where('user_id', $user->id)->first();

            if (!$affiliate_user) {
                return response()->json([
                    'result' => true,
                    'data' => [
                        'total_clicks' => 0,
                        'total_orders' => 0,
                        'total_delivered' => 0,
                        'total_cancelled' => 0
                    ]
                ], 200);
            }

            $query = AffiliateStats::query();
            $query->select(
                DB::raw('SUM(no_of_click) AS count_click,
                     SUM(no_of_order_item) AS count_item,
                     SUM(no_of_delivered) AS count_delivered,
                     SUM(no_of_cancel) AS count_cancel')
            );

            // Apply date filters
            if ($type == 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($type == '7') {
                $query->whereDate('created_at', '>=', Carbon::now()->subDays(7));
            } elseif ($type == '30') {
                $query->whereDate('created_at', '>=', Carbon::now()->subDays(30));
            }

            // Use affiliate_user_id (from affiliate_users table), not user_id
            $query->where('affiliate_user_id', $affiliate_user->id);
            $stats = $query->first();

            return response()->json([
                'result' => true,
                'data' => [
                    'total_clicks' => (int)($stats->count_click ?? 0),
                    'total_orders' => (int)($stats->count_item ?? 0),
                    'total_delivered' => (int)($stats->count_delivered ?? 0),
                    'total_cancelled' => (int)($stats->count_cancel ?? 0)
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get affiliate logs
     */
    public function affiliateLogs(Request $request)
    {
        try {
            $user = Auth::user();
            $per_page = $request->per_page ?? 10;

            // Get logs where this user referred others
            $affiliate_logs = AffiliateLog::where('referred_by_user', $user->id)
                ->with([
                    'user',  // The referred user
                    'order_detail.product'
                ])
                ->latest()
                ->paginate($per_page);

            return response()->json([
                'result' => true,
                'data' => $affiliate_logs
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment history
     */
    public function paymentHistory(Request $request)
    {
        try {
            $affiliate_user = Auth::user()->affiliate_user;

            if (!$affiliate_user) {
                return response()->json([
                    'result' => false,
                    'message' => translate('Affiliate user not found')
                ], 404);
            }

            $per_page = $request->per_page ?? 10;
            $affiliate_payments = $affiliate_user->affiliate_payments()->paginate($per_page);

            return response()->json([
                'result' => true,
                'data' => $affiliate_payments
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get withdraw request history
     */
    public function withdrawHistory(Request $request)
    {
        try {
            $per_page = $request->per_page ?? 10;

            $withdraw_requests = AffiliateWithdrawRequest::where('user_id', Auth::user()->id)
                ->orderBy('id', 'desc')
                ->paginate($per_page);

            return response()->json([
                'result' => true,
                'data' => $withdraw_requests
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment settings
     */
    public function paymentSettings()
    {
        try {
            $affiliate_user = Auth::user()->affiliate_user;

            if (!$affiliate_user) {
                return response()->json([
                    'result' => false,
                    'message' => translate('Affiliate user not found')
                ], 404);
            }

            return response()->json([
                'result' => true,
                'data' => [
                    'paypal_email' => $affiliate_user->paypal_email,
                    'bank_information' => $affiliate_user->bank_information
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store payment settings
     */
    public function storePaymentSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'paypal_email' => 'nullable|email',
            'bank_information' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->all()
            ], 400);
        }

        try {
            $affiliate_user = Auth::user()->affiliate_user;

            if (!$affiliate_user) {
                return response()->json([
                    'result' => false,
                    'message' => translate('Affiliate user not found')
                ], 404);
            }

            $affiliate_user->paypal_email = $request->paypal_email;
            $affiliate_user->bank_information = $request->bank_information;
            $affiliate_user->save();

            return response()->json([
                'result' => true,
                'message' => translate('Affiliate payment settings has been updated successfully'),
                'data' => [
                    'paypal_email' => $affiliate_user->paypal_email,
                    'bank_information' => $affiliate_user->bank_information
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store withdraw request
     */
    public function storeWithdrawRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'result' => false,
                'message' => $validator->errors()->all()
            ], 400);
        }

        try {
            $minimum_withdraw = AffiliateConfig::where('type', 'minimum_affiliate_withdraw_amount')->first();
            $affiliate_user = Auth::user()->affiliate_user;

            if (!$affiliate_user) {
                return response()->json([
                    'result' => false,
                    'message' => translate('Affiliate user not found')
                ], 404);
            }

            if ($minimum_withdraw && $request->amount < $minimum_withdraw->value) {
                return response()->json([
                    'result' => false,
                    'message' => translate('Minimum withdraw amount is ') . $minimum_withdraw->value
                ], 400);
            }

            if ($request->amount > $affiliate_user->balance) {
                return response()->json([
                    'result' => false,
                    'message' => translate('Insufficient balance')
                ], 400);
            }

            $withdraw_request = new AffiliateWithdrawRequest;
            $withdraw_request->user_id = Auth::user()->id;
            $withdraw_request->amount = $request->amount;
            $withdraw_request->status = 0;
            $withdraw_request->save();

            $affiliate_user->balance = $affiliate_user->balance - $request->amount;
            $affiliate_user->save();

            return response()->json([
                'result' => true,
                'message' => translate('New withdraw request created successfully'),
                'data' => [
                    'withdraw_request' => $withdraw_request,
                    'remaining_balance' => $affiliate_user->balance
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get referral statistics
     */
    public function referralStats()
    {
        try {
            $user = Auth::user();

            $referral_count = User::where('referred_by', $user->id)->count();
            $referral_users = User::where('referred_by', $user->id)
                ->select('id', 'name', 'email', 'created_at')
                ->latest()
                ->get();

            return response()->json([
                'result' => true,
                'data' => [
                    'referral_code' => $user->referral_code,
                    'total_referrals' => $referral_count,
                    'referral_users' => $referral_users
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track affiliate link click
     */
    public function trackClick(Request $request)
    {
        try {
            $referralCode = $request->referral_code;

            if (!$referralCode) {
                return response()->json([
                    'result' => false,
                    'message' => 'No referral code provided'
                ], 400);
            }

            // Find the affiliate user by referral code
            $referredBy = User::where('referral_code', $referralCode)->first();

            if (!$referredBy) {
                return response()->json([
                    'result' => false,
                    'message' => 'Invalid referral code'
                ], 400);
            }

            // Get the affiliate_user record
            $affiliateUser = AffiliateUser::where('user_id', $referredBy->id)->first();

            if (!$affiliateUser || $affiliateUser->status != 1) {
                return response()->json([
                    'result' => false,
                    'message' => 'Affiliate user not found or not approved'
                ], 400);
            }

            // Track the click
            $this->processAffiliateStats($affiliateUser->id, 1, 0, 0, 0);

            // Store referral code in session/cookie for later
            return response()->json([
                'result' => true,
                'message' => 'Click tracked successfully',
                'data' => [
                    'referral_code' => $referralCode
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process affiliate stats (same as Blade version)
     */
    public function processAffiliateStats($affiliate_user_id, $no_click = 0, $no_item = 0, $no_delivered = 0, $no_cancel = 0)
    {
        $affiliate_stats = AffiliateStats::whereDate('created_at', Carbon::today())
            ->where("affiliate_user_id", $affiliate_user_id)
            ->first();

        if (!$affiliate_stats) {
            $affiliate_stats = new AffiliateStats;
            $affiliate_stats->no_of_order_item = 0;
            $affiliate_stats->no_of_delivered = 0;
            $affiliate_stats->no_of_cancel = 0;
            $affiliate_stats->no_of_click = 0;
            $affiliate_stats->created_at = Carbon::today();
        }

        $affiliate_stats->no_of_order_item += $no_item;
        $affiliate_stats->no_of_delivered += $no_delivered;
        $affiliate_stats->no_of_cancel += $no_cancel;
        $affiliate_stats->no_of_click += $no_click;
        $affiliate_stats->affiliate_user_id = $affiliate_user_id;
        $affiliate_stats->updated_at = now();

        $affiliate_stats->save();

        return $affiliate_stats;
    }
}
