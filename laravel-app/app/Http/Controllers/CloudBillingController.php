<?php

namespace App\Http\Controllers;

use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionPayment;
use App\Cloud\CloudTenant;
use App\Services\Cloud\CloudCheckoutService;
use App\Services\Cloud\CloudSubscriptionService;
use Illuminate\Http\Request;

/**
 * Tenant billing history and platform-admin manual activation.
 * Tenant id always comes from the signed-in company, never from the form.
 */
class CloudBillingController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->attributes->get('cloudTenant');
        $payments = CloudSubscriptionPayment::where('cloud_tenant_id', $tenant->id)
            ->with('items')
            ->orderBy('id', 'desc')
            ->get();

        return view('cloud.portal.billing', [
            'tenant' => $tenant,
            'payments' => $payments,
        ]);
    }

    public function checkout(Request $request)
    {
        $tenant = $request->attributes->get('cloudTenant');
        $ids = array_map('intval', (array) $request->input('subscription_ids', []));
        $subscriptions = CloudSubscription::where('cloud_tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->get()
            ->all();
        try {
            $url = app(CloudCheckoutService::class)->startMany($tenant, $subscriptions, $request->input('method'));
        } catch (\Exception $e) {
            return redirect()->route('cloud.subscribe')->with('not_permitted', $e->getMessage());
        }

        return redirect()->away($url);
    }

    public function receipt(Request $request, $paymentId)
    {
        $tenant = $request->attributes->get('cloudTenant');
        $payment = CloudSubscriptionPayment::where('cloud_tenant_id', $tenant->id)->find($paymentId);
        if (! $payment || $payment->status !== CloudSubscriptionPayment::PAID) {
            abort(404);
        }
        $payment->load('items.subscription.plan.module', 'subscription.plan.module');

        return view('cloud.portal.receipt', [
            'tenant' => $tenant,
            'payment' => $payment,
        ]);
    }

    public function adminIndex(Request $request)
    {
        $this->authorizePlatform();
        $query = CloudSubscriptionPayment::query()->orderBy('id', 'desc');
        if ($request->query('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        }
        if ($request->query('provider')) {
            $query->where('provider', (string) $request->query('provider'));
        }
        if ($request->query('tenant')) {
            $query->where('cloud_tenant_id', (int) $request->query('tenant'));
        }

        return view('cloud.admin.billing', [
            'payments' => $query->limit(200)->get(),
            'tenants' => CloudTenant::orderBy('name')->get(),
        ]);
    }

    public function manual(Request $request)
    {
        $this->authorizePlatform();
        $data = $request->validate([
            'cloud_tenant_id' => 'required|integer',
            'cloud_subscription_id' => 'required|integer',
            'method' => 'required|string|max:32',
            'reference' => 'nullable|string|max:191',
            'reason' => 'required|string|max:500',
        ]);
        $tenant = CloudTenant::findOrFail($data['cloud_tenant_id']);
        $subscription = CloudSubscription::where('cloud_tenant_id', $tenant->id)->findOrFail($data['cloud_subscription_id']);
        app(CloudSubscriptionService::class)->recordManualPayment($tenant, $subscription, $request->user()->id, $data);

        return redirect()->route('cloud.admin.billing')->with('message', 'Manual payment recorded.');
    }

    public function selfActivate(Request $request)
    {
        return response('A company cannot mark its own subscription paid.', 403);
    }

    protected function authorizePlatform()
    {
        $user = auth()->user();
        if (! $user || ! in_array((int) $user->role_id, [1, 2], true)) {
            abort(403);
        }
    }
}
