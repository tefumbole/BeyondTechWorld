<?php

namespace App\Http\Controllers;

use App\Cloud\CloudPaymentMethod;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionEvent;
use App\Cloud\CloudSubscriptionEventType;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\Services\Cloud\CloudPublicSignup;
use App\Services\Cloud\CloudSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CloudAdminController extends Controller
{
    public function index()
    {
        $this->authorizePlatform();
        $plans = CloudPlan::with('module')->orderBy('sort_order')->orderBy('id')->get();
        $tenants = CloudTenant::with(['subscriptions.plan.module', 'memberships.user'])->orderByDesc('id')->limit(100)->get();
        $methods = CloudPaymentMethod::orderBy('sort_order')->get();
        $signupOpen = CloudPublicSignup::open();

        return view('cloud.admin.index', compact('plans', 'tenants', 'methods', 'signupOpen'));
    }

    public function setPublicSignup(Request $request)
    {
        $this->authorizePlatform();
        $open = $request->input('open') === '1';
        CloudPublicSignup::set($open);
        $internal = CloudTenant::where('type', CloudTenantType::INTERNAL)->first();
        if ($internal) {
            CloudSubscriptionEvent::create([
                'cloud_tenant_id' => $internal->id,
                'event' => CloudSubscriptionEventType::ADMIN_OVERRIDE,
                'actor_user_id' => Auth::id(),
                'payload' => json_encode(['public_signup' => $open ? 'open' : 'closed']),
            ]);
        }

        return redirect()->route('cloud.admin')->with('message', $open ? 'Public company signup is open.' : 'Public company signup is closed. Companies already created were not deleted.');
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

    public function grantTrial(Request $request, $tenantId, CloudSubscriptionService $subscriptions)
    {
        $this->authorizePlatform();
        $tenant = CloudTenant::findOrFail($tenantId);
        $plan = CloudPlan::where('active', true)->findOrFail($request->input('plan_id'));
        $subscriptions->grantTrial($tenant, $plan, Auth::id());

        return redirect()->route('cloud.admin')->with('message', 'Trial granted.');
    }

    public function extendSubscription($subscriptionId, CloudSubscriptionService $subscriptions)
    {
        $this->authorizePlatform();
        $subscription = CloudSubscription::findOrFail($subscriptionId);
        $subscriptions->extendSubscription($subscription, Auth::id());

        return redirect()->route('cloud.admin')->with('message', 'Subscription extended.');
    }

    public function suspend($subscriptionId, CloudSubscriptionService $subscriptions)
    {
        $this->authorizePlatform();
        $subscription = CloudSubscription::findOrFail($subscriptionId);
        $subscriptions->suspend($subscription, Auth::id());

        return redirect()->route('cloud.admin')->with('message', 'Subscription suspended. Existing records stay available to view.');
    }

    public function reactivate($subscriptionId, CloudSubscriptionService $subscriptions)
    {
        $this->authorizePlatform();
        $subscription = CloudSubscription::findOrFail($subscriptionId);
        $subscriptions->reactivate($subscription, Auth::id());

        return redirect()->route('cloud.admin')->with('message', 'Subscription reactivated.');
    }

    public function cancel(Request $request, $subscriptionId, CloudSubscriptionService $subscriptions)
    {
        $this->authorizePlatform();
        $subscription = CloudSubscription::findOrFail($subscriptionId);
        if ($request->input('when') === 'period_end') {
            $subscriptions->cancelAtPeriodEnd($subscription, Auth::id());
        } else {
            $subscriptions->cancelNow($subscription, Auth::id());
        }

        return redirect()->route('cloud.admin')->with('message', 'Subscription cancellation recorded. Company data was not deleted.');
    }

    public function suspendCompany($id)
    {
        return $this->setCompanyStatus($id, CloudTenantStatus::SUSPENDED, 'Company suspended. Records were not deleted.');
    }

    public function reactivateCompany($id)
    {
        return $this->setCompanyStatus($id, CloudTenantStatus::ACTIVE, 'Company reactivated.');
    }

    protected function setCompanyStatus($id, $status, $message)
    {
        $this->authorizePlatform();
        $tenant = CloudTenant::findOrFail($id);
        if ($tenant->type === CloudTenantType::INTERNAL) {
            return redirect()->route('cloud.admin')->with('not_permitted', 'The internal company is not suspended from this screen.');
        }
        $tenant->status = $status;
        $tenant->save();
        CloudSubscriptionEvent::create([
            'cloud_tenant_id' => $tenant->id,
            'event' => CloudSubscriptionEventType::ADMIN_OVERRIDE,
            'actor_user_id' => Auth::id(),
            'payload' => json_encode(['company_status' => $status]),
        ]);

        return redirect()->route('cloud.admin')->with('message', $message);
    }

    protected function authorizePlatform()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403, 'Only a platform administrator can manage subscriptions.');
        }
    }
}
