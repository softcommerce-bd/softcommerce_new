<?php

namespace App\Http\Controllers;

use App\Models\Bdcourier;
use App\Models\PathaoCourierSetting;
use App\Models\SteadfastSetting;
use Illuminate\Http\Request;

class CourierSetupController extends Controller
{
    public function index()
    {
        $steadfast = SteadfastSetting::first();
        $pathao = PathaoCourierSetting::first();
        $bd_courier = Bdcourier::first();
        return view('backend.courier_credential.index',compact('steadfast','pathao','bd_courier'));
    }
}
