<?php

namespace App\Http\Controllers;

use App\Cloud\CloudPaymentMethod;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionPayment;
use App\Cloud\CloudTenant;
use App\Services\Cloud\CloudCheckoutService;
use App\Services\Cloud\CloudPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CloudPortalController extends Controller
{
    protected $portal;

    public function __construct(CloudPortalService $portal)
    {
        $this->portal = $portal;
    }

    public function showLogin()
    {
        if ($this->portal->membershipFor(Auth::user())) {
            return redirect()->route('cloud.home');
        }

        return view('cloud.portal.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_active' => 1])) {
            return back()->with('not_permitted', 'Those sign-in details were not recognised.')->withInput();
        }
        if (! $this->portal->membershipFor(Auth::user())) {
            Auth::logout();

            return back()->with('not_permitted', 'This sign-in is for company portals. Use the staff sign-in for the main system.');
        }
        $request->session()->regenerate();

        return redirect()->route('cloud.home');
    }

    public function showRegister()
    {
        return view('cloud.portal.register', [
            'onboardingOpen' => (bool) config('cloud.public_onboarding'),
        ]);
    }

    public function register(Request $request)
    {
        if (! config('cloud.public_onboarding')) {
            return redirect()->route('cloud.register')->with('not_permitted', 'Company signup is not open yet.');
        }
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191|unique:users,email',
            'phone' => 'required|string|max:32',
            'password' => 'required|string|min:8|confirmed',
            'company_name' => 'required|string|max:191',
            'country' => 'nullable|string|max:8',
        ]);
        list($user) = $this->portal->register($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('cloud.home')->with('message', 'Your company portal is ready. Start a 24-hour trial when you want a module.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();

        return redirect()->route('cloud.login');
    }

    public function home(Request $request)
    {
        $tenant = $this->tenant($request);
        $subscriptions = CloudSubscription::with('plan.module')->where('cloud_tenant_id', $tenant->id)->orderByDesc('id')->get();

        return view('cloud.portal.home', [
            'tenant' => $tenant,
            'subscriptions' => $subscriptions,
            'heroUrl' => $tenant->hero_path ? route('cloud.hero', ['uuid' => $tenant->uuid]) : null,
        ]);
    }

    public function subscribe(Request $request)
    {
        $tenant = $this->tenant($request);

        return view('cloud.portal.subscribe', [
            'tenant' => $tenant,
            'plans' => CloudPlan::with('module')->where('active', true)->orderBy('sort_order')->get(),
            'methods' => CloudPaymentMethod::where('active', true)->orderBy('sort_order')->get(),
            'subscriptions' => CloudSubscription::where('cloud_tenant_id', $tenant->id)->get()->keyBy('cloud_plan_id'),
        ]);
    }

    public function startTrial(Request $request, $planId)
    {
        $tenant = $this->tenant($request);
        $plan = CloudPlan::where('active', true)->findOrFail($planId);
        try {
            $this->portal->startTrial($tenant, $plan);
        } catch (\Exception $e) {
            return redirect()->route('cloud.subscribe')->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('cloud.home')->with('message', $plan->name.' trial has started.');
    }

    public function pay(Request $request, $subscriptionId)
    {
        $tenant = $this->tenant($request);
        $data = $request->validate(['method' => 'required|string|max:32']);
        $subscription = CloudSubscription::where('cloud_tenant_id', $tenant->id)->findOrFail($subscriptionId);
        try {
            $url = app(CloudCheckoutService::class)->start($tenant, $subscription, $data['method']);
        } catch (\Exception $e) {
            return redirect()->route('cloud.subscribe')->with('not_permitted', $e->getMessage());
        }

        return redirect()->away($url);
    }

    public function payReturn(Request $request, $paymentId)
    {
        $payment = CloudSubscriptionPayment::findOrFail($paymentId);
        $tenant = $this->tenant($request);
        if ((int) $payment->cloud_tenant_id !== (int) $tenant->id) {
            abort(403);
        }
        $paid = false;
        if ($request->query('session_id') && $payment->provider === 'stripe') {
            $paid = app(CloudCheckoutService::class)->confirmVisa($payment, $request->query('session_id'));
        }

        return redirect()->route('cloud.home')->with(
            $paid ? 'message' : 'not_permitted',
            $paid ? 'Payment confirmed. This module is active.' : 'Payment is not confirmed yet. The trial stays as it is until MoMo or VISA confirms it.'
        );
    }

    public function settings(Request $request)
    {
        $tenant = $this->tenant($request);

        return view('cloud.portal.settings', [
            'tenant' => $tenant,
            'summary' => $this->portal->setting($tenant, 'business_summary'),
            'services' => $this->portal->setting($tenant, 'services'),
            'rules' => $this->portal->setting($tenant, 'business_rules'),
            'heroUrl' => $tenant->hero_path ? route('cloud.hero', ['uuid' => $tenant->uuid]) : null,
        ]);
    }

    public function saveSettings(Request $request)
    {
        $tenant = $this->tenant($request);
        $data = $request->validate([
            'system_name' => 'required|string|max:191',
            'business_summary' => 'nullable|string|max:5000',
            'services' => 'nullable|string|max:5000',
            'business_rules' => 'nullable|string|max:8000',
            'hero' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
        ]);
        $this->portal->saveBusinessRules($tenant, $data);
        if ($request->hasFile('hero')) {
            try {
                $this->portal->storeHero($tenant, $request->file('hero'));
            } catch (\Exception $e) {
                return redirect()->route('cloud.settings')->with('not_permitted', $e->getMessage());
            }
        }

        return redirect()->route('cloud.settings')->with('message', 'Company settings saved.');
    }

    public function hero($uuid)
    {
        $tenant = CloudTenant::where('uuid', $uuid)->first();
        if (! $tenant) {
            abort(404);
        }
        $path = $this->portal->heroFile($tenant);
        if (! $path) {
            abort(404);
        }

        return response()->file($path);
    }

    protected function tenant(Request $request)
    {
        $tenant = $request->attributes->get('cloudTenant');
        if (! $tenant) {
            abort(403);
        }

        return $tenant;
    }
}
