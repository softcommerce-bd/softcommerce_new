<?php

namespace App\Http\Controllers;

use App\Models\Bdcourier;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class BDcourierController extends Controller
{
    protected $bdCourier;

    public function __construct(Bdcourier  $bdCourier)
    {
        $this->bdCourier = $bdCourier;
    }

    public function show():View
    {
        $setting = $this->bdCourier->first();
        return view('backend.bdcourier.show',compact('setting'));
    }


    public function createOrUpdate(Request $request):RedirectResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);


        $this->bdCourier->updateOrCreate(
            [],
            [
                'token' => $request->token,
            ]
        );

        flash(translate('Token updated successfully.'))->success();
        return redirect()->back();
    }
}
