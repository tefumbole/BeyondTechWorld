<?php

namespace App\Http\Controllers;

use App\Cloud\CloudPaymentMethod;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CloudAdminController extends Controller
{
    public function index()
    {
        $this->authorizePlatform();
        $plans = CloudPlan::with('module')->orderBy('sort_order')->orderBy('id')->get();
        $tenants = CloudTenant::with(['subscriptions.plan'])->orderByDesc('id')->limit(100)->get();
        $methods = CloudPaymentMethod::orderBy('sort_order')->get();

        return view('cloud.admin.index', compact('plans', 'tenants', 'methods'));
    }

    public function updatePrice(Request $request, $id)
    {
        $this->authorizePlatform();
        $plan = CloudPlan::findOrFail($id);
        $data = $request->validate([
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'trial_value' => 'required|integer|min:1|max:720',
            'trial_unit' => 'required|in:HOUR,MONTH',
            'active' => 'nullable|boolean',
        ]);
        $plan->price = $data['price'];
        $plan->currency = strtoupper($data['currency']);
        $plan->trial_value = (int) $data['trial_value'];
        $plan->trial_unit = $data['trial_unit'];
        $plan->active = $request->has('active');
        $plan->save();

        return redirect()->route('cloud.admin')->with('message', $plan->name.' price saved. Companies already in a trial keep the price quoted when they started.');
    }

    protected function authorizePlatform()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403, 'Only a platform administrator can manage subscriptions.');
        }
    }
}
