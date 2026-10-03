<?php

namespace App\Http\Controllers;

use App\Cloud\CloudPaymentMethod;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionPayment;
use App\Cloud\CloudTenant;
use App\Services\Cloud\CloudCheckoutService;
use App\Services\Cloud\CloudExistingAccountException;
use App\Services\Cloud\CloudOnboardingService;
use App\Services\Cloud\CloudPortalService;
use App\Services\Cloud\CloudPublicSignup;
use App\Services\Cloud\CloudTenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        $token = Str::random(40);
        session(['cloud_onboard_token' => $token]);
        $open = CloudPublicSignup::open();
        $plans = $open ? app(CloudOnboardingService::class)->plans() : collect();

        return view('cloud.portal.register', [
            'onboardingOpen' => $open,
            'plans' => $plans,
            'quote' => app(CloudOnboardingService::class)->quote($plans->map(function ($plan) {
                return $plan->module->code;
            })->all()),
            'onboardToken' => $token,
            'welcome' => config('cloud.trial_welcome'),
            'selected' => (string) request('service'),
            'paymentNotice' => config('cloud.payment_pending_notice'),
        ]);
    }

    public function register(Request $request)
    {
        if (! $this->registrationOpen($request)) {
            return redirect()->route('cloud.register')->with('not_permitted', 'Company signup is not open yet.');
        }
        if (trim((string) $request->input('full_name')) !== '' && trim((string) $request->input('first_name')) === '') {
            $parts = app(\App\Services\Cloud\CloudPhoneNameResolver::class)->split($request->input('full_name'));
            $request->merge([
                'first_name' => $parts[0],
                'last_name' => $parts[1],
            ]);
        }
        if (trim((string) $request->input('company_name')) === '') {
            $request->merge([
                'company_name' => trim($request->input('first_name').' '.$request->input('last_name')),
            ]);
        }
        $data = $this->companyInput($request, true);
        if (! app(\App\Services\Cloud\CloudSignupOtp::class)->matches($data['phone'])) {
            return redirect()->route('cloud.register')->with('not_permitted', 'Verify the phone number before continuing.')->withInput();
        }
        $data['account_kind'] = session('cloud_account_kind') === 'personal' ? 'personal' : (isset($data['account_kind']) ? $data['account_kind'] : 'company');
        try {
            list($user, $tenant) = app(CloudOnboardingService::class)->register($data);
        } catch (CloudExistingAccountException $e) {
            return redirect()->route('cloud.login')->with('not_permitted', $e->getMessage());
        } catch (\Exception $e) {
            return redirect()->route('cloud.register')->with('not_permitted', $e->getMessage())->withInput();
        }
        Auth::login($user);
        $request->session()->regenerate();
        session([CloudTenantResolver::SESSION_KEY => $tenant->id]);

        $message = config('cloud.trial_welcome');
        if ($request->input('start_mode') === 'pay') {
            $message = 'Your company is open on the 24-hour trial. Pay records that you want to pay the monthly total. It does not collect money from this page.';
        }

        return redirect()->route('cloud.home')->with('message', $message);
    }

    protected function registrationOpen(Request $request)
    {
        if (CloudPublicSignup::open()) {
            return true;
        }
        $onboard = (string) $request->input('onboard_token', '');

        return $onboard !== '' && is_array(Cache::get('cloud-onboard-done:'.$onboard));
    }

    public function addCompany(Request $request)
    {
        $data = $this->companyInput($request, false);
        try {
            list($user, $tenant) = app(CloudOnboardingService::class)->addCompany(Auth::user(), $data);
        } catch (\Exception $e) {
            return redirect()->route('cloud.home')->with('not_permitted', $e->getMessage());
        }
        session([CloudTenantResolver::SESSION_KEY => $tenant->id]);

        return redirect()->route('cloud.home')->with('message', $tenant->name.' is ready. '.config('cloud.trial_welcome'));
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
        $token = Str::random(40);
        session(['cloud_onboard_token' => $token]);

        return view('cloud.portal.home', [
            'tenant' => $tenant,
            'subscriptions' => $subscriptions,
            'heroUrl' => $tenant->hero_path ? route('cloud.hero', ['uuid' => $tenant->uuid]) : null,
            'logoUrl' => $tenant->logo_path ? route('cloud.logo', ['uuid' => $tenant->uuid]) : null,
            'checklist' => app(CloudOnboardingService::class)->checklist($tenant),
            'welcome' => config('cloud.trial_welcome'),
            'ended' => config('cloud.trial_ended_notice'),
            'paymentNotice' => config('cloud.payment_pending_notice'),
            'plans' => app(CloudOnboardingService::class)->plans(),
            'onboardToken' => $token,
            'memberships' => \App\Cloud\CloudTenantMembership::with('cloudTenant')
                ->where('user_id', Auth::id())
                ->where('status', \App\Cloud\CloudMembershipStatus::ACTIVE)
                ->get(),
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
            'logo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        $this->portal->saveBusinessRules($tenant, $data);
        try {
            if ($request->hasFile('hero')) {
                $this->portal->storeHero($tenant, $request->file('hero'));
            }
            if ($request->hasFile('logo')) {
                $this->portal->storeLogo($tenant, $request->file('logo'));
            }
        } catch (\Exception $e) {
            return redirect()->route('cloud.settings')->with('not_permitted', $e->getMessage());
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

    public function logo($uuid)
    {
        $tenant = CloudTenant::where('uuid', $uuid)->first();
        if (! $tenant) {
            abort(404);
        }
        $path = $this->portal->logoFile($tenant);
        if (! $path) {
            abort(404);
        }

        return response()->file($path);
    }

    public function messaging(Request $request)
    {
        $tenant = $this->tenant($request);
        $connected = \Illuminate\Support\Facades\Schema::hasTable('cloud_whatsapp_connections')
            && \App\Cloud\CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)->where('status', 'ACTIVE')->exists();
        $smsConnection = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('cloud_sms_connections')) {
            $smsConnection = \Illuminate\Support\Facades\DB::table('cloud_sms_connections')
                ->where('cloud_tenant_id', $tenant->id)
                ->orderByDesc('id')
                ->first();
        }
        $smsReady = $smsConnection && $smsConnection->status === 'ACTIVE' && $smsConnection->sending_enabled;
        $hub = \Illuminate\Support\Facades\Schema::hasTable('cloud_sms_messages')
            ? app(\App\Services\Messaging\MessagingHub::class)
            : null;
        $usage = $hub ? $hub->usage($tenant->id) : collect();
        $credit = $hub && \Illuminate\Support\Facades\Schema::hasTable('cloud_sms_ledger')
            ? app(\App\Services\Messaging\SmsCreditLedger::class)->available($tenant->id)
            : 0;

        return view('cloud.portal.messaging', [
            'tenant' => $tenant,
            'connected' => $connected,
            'smsReady' => $smsReady,
            'smsConnection' => $smsConnection,
            'credit' => $credit,
            'usage' => $usage,
            'entitled' => app(\App\Services\Cloud\CloudModuleAccessService::class)->canWriteCapability($tenant, 'messaging'),
            'paymentNotice' => config('cloud.payment_pending_notice'),
        ]);
    }

    protected function companyInput(Request $request, $withAccount)
    {
        $rules = [
            'company_name' => 'required|string|max:191',
            'system_name' => 'nullable|string|max:191',
            'legal_name' => 'nullable|string|max:191',
            'company_phone' => 'nullable|string|max:32',
            'company_email' => 'nullable|email|max:191',
            'country' => 'nullable|string|max:64',
            'start_mode' => 'nullable|in:trial,pay',
            'signature' => 'nullable|string|max:500000',
            'city' => 'nullable|string|max:191',
            'address' => 'nullable|string|max:191',
            'timezone' => 'nullable|string|max:64',
            'currency' => 'nullable|string|size:3',
            'modules' => 'required|array|min:1',
            'modules.*' => 'string|max:64',
            'onboard_token' => 'required|string|max:80',
            'account_kind' => 'nullable|in:personal,company',
        ];
        if ($withAccount && Schema::hasColumn('users', 'username')) {
            $rules['username'] = 'required|string|min:3|max:100|regex:/^[A-Za-z0-9._-]+$/|unique:users,username';
        } elseif ($withAccount) {
            $rules['username'] = 'nullable|string|max:100';
        }
        if ($withAccount) {
            $rules['first_name'] = 'required|string|max:80';
            $rules['last_name'] = 'required|string|max:80';
            $rules['email'] = 'required|email|max:191';
            $rules['phone'] = 'required|string|max:32';
            $rules['password'] = 'required|string|min:8|confirmed';
        } else {
            $rules['phone'] = 'nullable|string|max:32';
        }

        return $request->validate($rules);
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
