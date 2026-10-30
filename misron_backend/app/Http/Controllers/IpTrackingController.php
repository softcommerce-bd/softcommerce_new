<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BlockedIp;

class IpTrackingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:view_all_customers'])->only('index');
    }

    public function index(Request $request)
    {
        $sort_search = null;
        $sort_status = null;

        $ips = BlockedIp::with(['user', 'blockedBy'])->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search != null) {
            $sort_search = $request->search;
            $ips->where(function ($q) use ($sort_search) {
                $q->where('ip_address', 'like', '%' . $sort_search . '%')
                  ->orWhere('customer_name', 'like', '%' . $sort_search . '%')
                  ->orWhere('customer_phone', 'like', '%' . $sort_search . '%')
                  ->orWhere('customer_email', 'like', '%' . $sort_search . '%');
            });
        }

        if ($request->has('status') && $request->status != null) {
            $sort_status = $request->status;
            $ips->where('status', $sort_status);
        }

        $ips = $ips->paginate(20);

        return view('backend.customer.ip_tracking.index', compact('ips', 'sort_search', 'sort_status'));
    }

    public function toggleBlock($id)
    {
        if (env('DEMO_MODE') == 'On') {
            flash(translate('Data can not change in demo mode.'))->info();
            return back();
        }

        $ip = BlockedIp::findOrFail(decrypt($id));

        if ($ip->status == 'blocked') {
            $ip->status = 'active';
            $ip->blocked_at = null;
            $ip->blocked_by = null;
            flash(translate('IP has been unblocked successfully'))->success();
        } else {
            $ip->status = 'blocked';
            $ip->blocked_at = now();
            $ip->blocked_by = auth()->id();
            flash(translate('IP has been blocked successfully'))->success();
        }

        $ip->save();

        return back();
    }

    public function destroy($id)
    {
        if (env('DEMO_MODE') == 'On') {
            flash(translate('Data can not change in demo mode.'))->info();
            return back();
        }

        BlockedIp::findOrFail($id)->delete();
        flash(translate('IP record has been deleted successfully'))->success();

        return back();
    }

    public function bulkDelete(Request $request)
    {
        if (env('DEMO_MODE') == 'On') {
            return 0;
        }

        if ($request->id) {
            foreach ($request->id as $ip_id) {
                BlockedIp::destroy($ip_id);
            }
        }

        return 1;
    }

    public function blockAllByUser($userId)
    {
        if (env('DEMO_MODE') == 'On') {
            flash(translate('Data can not change in demo mode.'))->info();
            return back();
        }

        BlockedIp::where('user_id', $userId)
            ->where('status', '!=', 'blocked')
            ->update([
                'status' => 'blocked',
                'blocked_at' => now(),
                'blocked_by' => auth()->id(),
            ]);

        flash(translate('All IPs for this customer have been blocked'))->success();

        return back();
    }

    public function unblockAllByUser($userId)
    {
        if (env('DEMO_MODE') == 'On') {
            flash(translate('Data can not change in demo mode.'))->info();
            return back();
        }

        BlockedIp::where('user_id', $userId)
            ->where('status', 'blocked')
            ->update([
                'status' => 'active',
                'blocked_at' => null,
                'blocked_by' => null,
            ]);

        flash(translate('All IPs for this customer have been unblocked'))->success();

        return back();
    }
}
