<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliveryModalController extends Controller
{
    public function index()
    {
        return view('backend.delivery_return.index');
    }
}
