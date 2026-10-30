<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\City;
use App\Models\Country;
use App\Http\Resources\V2\AddressCollection;
use App\Models\Address;
use App\Http\Resources\V2\CitiesCollection;
use App\Http\Resources\V2\CountriesCollection;
use App\Models\Order;
use App\Models\Upload;
use App\Services\Uploads\Base64ImageUpload;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use App\Models\Cart;
use Hash;
use Illuminate\Support\Facades\File;
use Storage;

class ProfileController extends Controller
{
    public function counters()
    {
        return response()->json([
            'cart_item_count' => Cart::where('user_id', auth()->user()->id)->count(),
            'wishlist_item_count' => Wishlist::where('user_id', auth()->user()->id)->count(),
            'order_count' => Order::where('user_id', auth()->user()->id)->count(),
        ]);
    }

    public function update(Request $request)
    {
        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found.")
            ]);
        }

        if(isset($request->name)){
            $user->name = $request->name;
        }
        if(isset($request->phone)){
            $user->phone = $request->phone;
        }

        if(isset($request->password)){
        if ($request->password != "") {
            $user->password = Hash::make($request->password);
        }
    }
        $user->save();

        return response()->json([
            'result' => true,
            'message' => translate("Profile information updated")
        ]);
    }

    public function update_device_token(Request $request)
    {
        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found.")
            ]);
        }

        $user->device_token = $request->device_token;


        $user->save();

        return response()->json([
            'result' => true,
            'message' => translate("device token updated")
        ]);
    }

    public function updateImage(Request $request)
    {
        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found."),
                'path' => ""
            ]);
        }

        $stored = app(Base64ImageUpload::class)->store($request->image, $request->filename, $user->id);
        if (is_string($stored)) {
            return response()->json([
                'result' => false,
                'message' => translate($stored),
                'path' => ""
            ]);
        }

        $user->avatar_original = $stored->id;
        $user->save();

        return response()->json([
            'result' => true,
            'message' => translate("Image updated"),
            'path' => uploaded_asset($stored->id)
        ]);
    }

    // not user profile image but any other base 64 image through uploader
    public function imageUpload(Request $request)
    {
        $user = User::find(auth()->user()->id);
        if(!$user){
            return response()->json([
                'result' => false,
                'message' => translate("User not found."),
                'path' => "",
                'upload_id' => 0
            ]);
        }

        $stored = app(Base64ImageUpload::class)->store($request->image, $request->filename, $user->id);
        if (is_string($stored)) {
            return response()->json([
                'result' => false,
                'message' => translate($stored),
                'path' => "",
                'upload_id' => 0
            ]);
        }

        return response()->json([
            'result' => true,
            'message' => translate("Image updated"),
            'path' => uploaded_asset($stored->id),
            'upload_id' => $stored->id
        ]);
    }

    public function checkIfPhoneAndEmailAvailable()
    {
        $phone_available = false;
        $email_available = false;
        $phone_available_message = translate("User phone number not found");
        $email_available_message = translate("User email  not found");

        $user = User::find(auth()->user()->id);

        if ($user->phone != null || $user->phone != "") {
            $phone_available = true;
            $phone_available_message = translate("User phone number found");
        }

        if ($user->email != null || $user->email != "") {
            $email_available = true;
            $email_available_message = translate("User email found");
        }
        return response()->json(
            [
                'phone_available' => $phone_available,
                'email_available' => $email_available,
                'phone_available_message' => $phone_available_message,
                'email_available_message' => $email_available_message,
            ]
        );
    }
}
