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
        return back()->with('not_permitted', 'Sign in with the WhatsApp code sent to your phone.');
    }

    public function sendLoginCode(Request $request)
    {
        $request->merge([
            'phone' => app(\App\Services\Cloud\CloudMbolePrice::class)->e164($request->input('phone'), $request->input('dial')),
        ]);
        $data = $request->validate(['phone' => 'required|string|max:32']);
        try {
            $code = app(\App\Services\Cloud\CloudSignupOtp::class)->send($data['phone']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $body = ['sent' => true];
        if (app()->environment('testing')) {
            $body['testing_code'] = $code;
        }

        return response()->json($body);
    }

    public function verifyLoginCode(Request $request)
    {
        $request->merge([
            'phone' => app(\App\Services\Cloud\CloudMbolePrice::class)->e164($request->input('phone'), $request->input('dial')),
        ]);
        $data = $request->validate([
            'phone' => 'required|string|max:32',
            'code' => 'required|string|max:12',
        ]);
        try {
            $phone = app(\App\Services\Cloud\CloudSignupOtp::class)->verify($data['phone'], $data['code']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $user = app(CloudOnboardingService::class)->accountForPhone($phone);
        if (! $user || ! $this->portal->membershipFor($user)) {
            return response()->json(['message' => 'This phone does not have an account yet.'], 422);
        }
        Auth::login($user);
        $request->session()->regenerate();
        app(\App\Services\Cloud\CloudCompanyAccess::class)->ensureRole($user, true);
        $next = $request->input('next') === 'messaging' ? route('cloud.messaging') : url('/admin');

        return response()->json(['verified' => true, 'redirect' => $next]);
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

    public function whatsappMai(Request $request)
    {
        if (\App\Support\MaiHost::isMainSite($request)) {
            return redirect()->away(\App\Support\MaiHost::url('/'));
        }
        $request->merge(['service' => 'MESSAGING']);

        return $this->showRegister();
    }

    public function whatsappMaiOpen()
    {
        if (Auth::check()) {
            return redirect()->route('whatsapp.mai.home');
        }
        if (! session('cloud_onboard_token')) {
            session(['cloud_onboard_token' => Str::random(40)]);
        }
        $membership = Auth::check() ? $this->portal->membershipFor(Auth::user()) : null;

        return view('cloud.portal.mai_open', [
            'onboardToken' => session('cloud_onboard_token'),
            'signedIn' => (bool) ($membership && $membership->cloudTenant),
            'countries' => \App\Support\CountryDialCodes::all(),
        ]);
    }

    public function whatsappMaiJoin(Request $request)
    {
        $request->merge([
            'mai' => '1',
            'mai_join' => '1',
            'modules' => ['MESSAGING'],
            'account_kind' => 'personal',
            'start_mode' => 'trial',
            'phone' => app(\App\Services\Cloud\CloudMbolePrice::class)->e164($request->input('phone'), $request->input('dial')),
        ]);
        if (trim((string) $request->input('onboard_token')) === '') {
            $request->merge(['onboard_token' => (string) session('cloud_onboard_token')]);
        }
        try {
            app(\App\Services\Cloud\CloudSignupOtp::class)->verify($request->input('phone'), $request->input('code'));
        } catch (\Exception $e) {
            return redirect()->route('whatsapp.mai.open')->with('not_permitted', $e->getMessage())->withInput();
        }
        session(['cloud_account_kind' => 'personal']);

        return $this->register($request);
    }

    public function whatsappMaiWorker()
    {
        $js = "self.addEventListener('install',function(){self.skipWaiting();});"
            ."self.addEventListener('activate',function(event){event.waitUntil(self.clients.claim());});"
            ."self.addEventListener('fetch',function(event){event.respondWith(fetch(event.request));});";

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/whatsapp-mai/',
        ]);
    }

    public function whatsappMaiSession(Request $request)
    {
        $phone = app(\App\Services\Cloud\CloudMbolePrice::class)->e164($request->input('phone'), $request->input('dial'));
        try {
            $phone = app(\App\Services\Cloud\CloudSignupOtp::class)->verify($phone, $request->input('code'));
        } catch (\Exception $e) {
            return redirect()->to(url('/login?mai=1'))->with('not_permitted', $e->getMessage())->withInput();
        }
        $user = app(CloudOnboardingService::class)->ensurePerson($phone, $request->input('full_name'));
        Auth::login($user);
        $request->session()->regenerate();
        if (\App\Services\Mai\MaiBusinessLine::is($phone)) {
            return redirect()->route('whatsapp.mai')->with('not_permitted', 'Use your personal WhatsApp number.');
        }
        $engine = app(\App\Services\Cloud\MaiWhatsAppEngine::class);
        $engine->open($phone);
        $state = $engine->status($phone, true);
        if (($state['status'] ?? '') === 'CONNECTED') {
            app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLinkOnce($phone);

            return redirect()->route('whatsapp.mai.inbox');
        }

        return redirect()->route('whatsapp.mai.link');
    }

    public function whatsappMaiBegin(Request $request)
    {
        $phone = app(\App\Services\Cloud\CloudMbolePrice::class)->e164($request->input('phone'), $request->input('dial'));
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            return response()->json(['message' => 'Enter a valid phone number.'], 422);
        }
        // A website OTP cannot open WhatsApp. Linking the phone (QR or link code) proves ownership.
        if (\App\Services\Mai\MaiBusinessLine::is($phone)) {
            return response()->json(['message' => 'Use your personal WhatsApp number. The business line stays separate.'], 422);
        }
        $name = trim((string) $request->input('full_name'));
        $user = app(CloudOnboardingService::class)->ensurePerson($phone, $name !== '' ? $name : null);
        Auth::login($user);
        $request->session()->regenerate();
        session(['mai_plan' => $request->input('mai_plan') === 'paid' ? 'paid' : 'trial']);
        $engine = app(\App\Services\Cloud\MaiWhatsAppEngine::class);
        $engine->open($phone);
        $state = $engine->status($phone, true);
        if (($state['status'] ?? '') === 'CONNECTED') {
            app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLinkOnce($phone);

            return response()->json([
                'status' => 'CONNECTED',
                'redirect' => route('whatsapp.mai.inbox'),
            ]);
        }
        if (empty($state['qr']) && ($state['status'] ?? '') !== 'CONNECTED') {
            $state = $engine->refreshQr($phone);
        }

        return response()->json([
            'status' => isset($state['status']) ? $state['status'] : 'NOT_CONNECTED',
            'qr' => isset($state['qr']) ? $state['qr'] : null,
            'qr_age_ms' => isset($state['qr_age_ms']) ? (int) $state['qr_age_ms'] : 0,
            'error' => isset($state['error']) ? $state['error'] : null,
        ]);
    }

    public function whatsappMaiPlan()
    {
        if (! Auth::check()) {
            return redirect()->to(url('/login?mai=1'));
        }
        if (! session('cloud_onboard_token')) {
            session(['cloud_onboard_token' => Str::random(40)]);
        }
        $user = Auth::user();
        $engine = app(\App\Services\Cloud\MaiWhatsAppEngine::class);
        $engine->open($user->phone);

        return view('cloud.portal.mai_plan', [
            'session' => $engine->status($user->phone, true),
            'onboardToken' => session('cloud_onboard_token'),
            'phone' => $user->phone,
            'periods' => app(\App\Services\Cloud\CloudMbolePrice::class)->periods(),
            'countries' => \App\Support\CountryDialCodes::all(),
        ]);
    }

    public function whatsappMaiPlanStore(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->to(url('/login?mai=1'));
        }
        $days = (int) $request->input('mai_days');
        if (! in_array($days, [3, 7, 15, 30], true)) {
            return redirect()->route('whatsapp.mai.plan')->with('not_permitted', 'Choose 3, 7, 15, or 30 days.');
        }
        if ($days === 3 || $request->input('start_mode') === 'trial') {
            return $this->beginFreeMai($request);
        }
        $method = strtoupper((string) $request->input('payment_method'));
        if (! in_array($method, ['MTN', 'ORANGE', 'VISA', 'CRYPTO'], true)) {
            return redirect()->route('whatsapp.mai.plan')->with('not_permitted', 'Choose MTN, Orange, VISA, or crypto.');
        }
        $user = Auth::user();
        $tenant = $this->maiCustomerTenant($user);
        if (! $tenant) {
            if (! session('cloud_onboard_token')) {
                session(['cloud_onboard_token' => Str::random(40)]);
            }
            $name = trim((string) $user->name);
            if ($name === '' || $name === 'Whats MAI') {
                $name = trim((string) $request->input('full_name'));
            }
            if ($name === '') {
                $name = 'Whats MAI';
            }
            try {
                list($user, $tenant) = app(CloudOnboardingService::class)->addCompany($user, [
                    'company_name' => $name,
                    'first_name' => $name,
                    'last_name' => $name,
                    'phone' => $user->phone,
                    'modules' => ['MESSAGING'],
                    'onboard_token' => (string) session('cloud_onboard_token'),
                    'account_kind' => 'personal',
                    'start_mode' => 'pay',
                    'mai_days' => $days,
                ]);
            } catch (\Exception $e) {
                return redirect()->route('whatsapp.mai.plan')->with('not_permitted', $e->getMessage());
            }
        } else {
            $plan = \App\Cloud\CloudPlan::whereHas('module', function ($query) {
                $query->where('code', \App\Cloud\CloudModuleCode::MESSAGING);
            })->where('active', true)->first();
            if ($plan) {
                app(\App\Services\Cloud\CloudSubscriptionService::class)->openPaidTerm($tenant, $plan, $days, $user->id);
            }
        }
        session([CloudTenantResolver::SESSION_KEY => $tenant->id]);
        $request->merge(['payment_method' => $method]);

        return $this->startMaiPayment($request, $tenant);
    }

    public function register(Request $request)
    {
        $fromMai = (string) $request->input('mai') === '1' || (string) $request->input('mai_join') === '1';
        $back = (string) $request->input('mai_join') === '1' ? 'whatsapp.mai.open' : ($fromMai ? 'whatsapp.mai' : 'cloud.register');
        $phone = app(\App\Services\Cloud\CloudMbolePrice::class)->e164($request->input('phone'), $request->input('dial'));
        if ($phone !== '') {
            $request->merge(['phone' => $phone]);
        }
        if (! $this->registrationOpen($request)) {
            return redirect()->route($back)->with('not_permitted', 'Company signup is not open yet.');
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
        $payMethod = strtoupper((string) $request->input('payment_method'));
        if ($request->input('start_mode') === 'pay' && ! in_array($payMethod, ['MTN', 'ORANGE', 'VISA', 'CRYPTO'], true)) {
            return redirect()->route($back)->with('not_permitted', 'Choose MTN, Orange, VISA, or crypto before paying.')->withInput();
        }
        if (! app(\App\Services\Cloud\CloudSignupOtp::class)->matches($data['phone'])) {
            return redirect()->route($back)->with('not_permitted', 'Verify the phone number before continuing.')->withInput();
        }
        $data['account_kind'] = session('cloud_account_kind') === 'personal' ? 'personal' : (isset($data['account_kind']) ? $data['account_kind'] : 'company');
        if ($fromMai) {
            $continued = $this->continueExistingMai($request, $data);
            if ($continued) {
                return $continued;
            }
        }
        try {
            list($user, $tenant) = app(CloudOnboardingService::class)->register($data);
        } catch (CloudExistingAccountException $e) {
            if ($fromMai) {
                $continued = $this->continueExistingMai($request, $data);
                if ($continued) {
                    return $continued;
                }
            }

            return redirect()->route($back)->with('not_permitted', $e->getMessage())->withInput();
        } catch (\Exception $e) {
            return redirect()->route($back)->with('not_permitted', $e->getMessage())->withInput();
        }
        Auth::login($user);
        $request->session()->regenerate();
        session([CloudTenantResolver::SESSION_KEY => $tenant->id]);
        if ($fromMai) {
            $this->openMaiWhatsApp($user->phone);
        }

        if ($request->input('start_mode') === 'pay') {
            if ($fromMai) {
                return $this->startMaiPayment($request, $tenant);
            }
            $subscriptions = CloudSubscription::where('cloud_tenant_id', $tenant->id)->get()->all();
            try {
                $url = app(\App\Services\Cloud\CloudCheckoutService::class)->startMany($tenant, $subscriptions, $payMethod);

                return redirect()->away($url);
            } catch (\Exception $e) {
                return redirect()->route('cloud.subscribe')->with('not_permitted', $e->getMessage());
            }
        }

        if ($fromMai && ($request->input('start_mode') === 'trial' || (int) $request->input('mai_days') === 3)) {
            return $this->finishFreeMai($request, $user, $tenant);
        }
        if ($fromMai && $request->input('start_mode') !== 'pay') {
            return redirect()->route('whatsapp.mai.plan')->with('message', 'Choose the free 3-day trial or a longer plan.');
        }

        return redirect()->route('cloud.home')->with('message', config('cloud.trial_welcome'));
    }

    /**
     * A phone already stored in Beyond continues into Whats MAI.
     * The signup code still does not connect WhatsApp.
     */
    protected function continueExistingMai(Request $request, array $data)
    {
        $user = app(CloudOnboardingService::class)->accountForPhone($data['phone']);
        if (! $user) {
            return null;
        }
        $full = trim((string) $request->input('full_name'));
        if ($full === '') {
            $full = trim((isset($data['first_name']) ? $data['first_name'] : '').' '.(isset($data['last_name']) ? $data['last_name'] : ''));
        }
        if ($full !== '' && $user->name !== $full) {
            $user->name = $full;
            $user->save();
        }
        $membership = $this->portal->membershipFor($user);
        if (! $membership || ! $membership->cloudTenant) {
            try {
                list($user, $tenant) = app(CloudOnboardingService::class)->addCompany($user, $data);
            } catch (\Exception $e) {
                return redirect()->route('whatsapp.mai.open')->with('not_permitted', $e->getMessage())->withInput();
            }
            Auth::login($user);
            $request->session()->regenerate();
            session([CloudTenantResolver::SESSION_KEY => $tenant->id]);
            $this->openMaiWhatsApp($data['phone']);
            if ($request->input('start_mode') === 'trial' || (int) $request->input('mai_days') === 3) {
                return $this->finishFreeMai($request, $user, $tenant);
            }
            if ($request->input('start_mode') === 'pay') {
                return $this->startMaiPayment($request, $tenant);
            }

            return redirect()->route('whatsapp.mai.plan')->with('message', 'A link was sent to this phone. Choose a 3-day trial or a subscription.');
        }
        Auth::login($user);
        $request->session()->regenerate();
        $this->openMaiWhatsApp($data['phone']);
        if ($request->input('start_mode') === 'trial' || (int) $request->input('mai_days') === 3) {
            return $this->finishFreeMai($request, $user, $membership->cloudTenant);
        }

        return redirect()->route('whatsapp.mai.plan')->with('message', 'Choose the free 3-day trial or a longer plan. The phone link is sent after that.');
    }

    public function whatsappMaiPay(Request $request, $paymentId)
    {
        $payment = \App\Cloud\CloudSubscriptionPayment::find($paymentId);
        if (! $payment) {
            return redirect()->route('whatsapp.mai.plan')->with('not_permitted', 'That payment was not found.');
        }
        $paid = $payment->status === \App\Cloud\CloudSubscriptionPayment::PAID;
        if (! $paid && $payment->provider === 'stripe') {
            $sessionId = trim((string) $request->query('session_id', $payment->provider_reference));
            $paid = $sessionId !== '' && app(\App\Services\StripeCheckoutService::class)->isPaid($sessionId);
        } elseif (! $paid && $payment->provider_reference) {
            $phone = $payment->cloudTenant ? preg_replace('/\D/', '', (string) $payment->cloudTenant->phone) : null;
            $outcome = app(\App\Services\CampayPayoutService::class)->collectionOutcome(
                $payment->provider_reference,
                (int) round((float) $payment->amount),
                $phone
            );
            $paid = ! empty($outcome['confirmed']);
        }
        if ($paid && $payment->status !== \App\Cloud\CloudSubscriptionPayment::PAID) {
            try {
                app(\App\Services\Cloud\CloudCheckoutService::class)->markPaidFromProvider($payment);
            } catch (\Exception $e) {
            }
            $payment = $payment->fresh();
        }
        if ($payment && $payment->status === \App\Cloud\CloudSubscriptionPayment::PAID) {
            $tenant = $payment->cloudTenant;
            $account = $tenant ? app(CloudOnboardingService::class)->accountForPhone($tenant->phone) : null;
            if ($account) {
                Auth::login($account);
                $request->session()->regenerate();
                app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLink($account->phone);
            }

            return redirect()->route('whatsapp.mai.home')->with('message', 'Payment received. Whats MAI is active for this term.');
        }

        return view('cloud.portal.mai_pay', ['payment' => $payment]);
    }

    public function whatsappMaiEnter(Request $request, $user)
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('whatsapp.mai')->with('not_permitted', 'This link has expired. Sign in again from Whats MAI.');
        }
        $account = \App\User::find($user);
        if (! $account) {
            return redirect()->route('whatsapp.mai')->with('not_permitted', 'This link does not match an account.');
        }
        Auth::login($account);
        $request->session()->regenerate();
        $this->openMaiWhatsApp($account->phone);

        return redirect()->route('whatsapp.mai.inbox');
    }

    public function whatsappMaiHome()
    {
        if (! Auth::check()) {
            return redirect()->route('whatsapp.mai');
        }
        $user = Auth::user();
        $engine = app(\App\Services\Cloud\MaiWhatsAppEngine::class);
        $engine->open($user->phone);

        $state = $engine->status($user->phone, true);
        if (($state['status'] ?? '') === 'CONNECTED' && ! \App\Services\Mai\MaiBusinessLine::is($user->phone)) {
            app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLinkOnce($user->phone);

            return redirect()->route('whatsapp.mai.inbox');
        }
        $linked = ($state['status'] ?? '') === 'CONNECTED';
        $open = preg_replace('/\D/', '', (string) request('chat'));
        $messages = [];
        if ($linked && $open !== '') {
            foreach ($engine->messages($user->phone) as $row) {
                $from = preg_replace('/\D/', '', (string) ($row['from'] ?? ''));
                if ($from !== '' && substr($from, -strlen($open)) === $open) {
                    $messages[] = $row;
                }
            }
        }

        return view('cloud.portal.mai_home', [
            'user' => $user,
            'session' => $state,
            'linkedPhone' => ! empty($state['phone']) ? $state['phone'] : $user->phone,
            'chats' => $linked ? $engine->chats($user->phone) : [],
            'openChat' => $open,
            'messages' => $messages,
        ]);
    }

    protected function beginFreeMai(Request $request)
    {
        $user = Auth::user();
        $tenant = $this->maiCustomerTenant($user);
        if (! $tenant) {
            if (! session('cloud_onboard_token')) {
                session(['cloud_onboard_token' => Str::random(40)]);
            }
            $name = trim((string) $user->name);
            if ($name === '' || $name === 'Whats MAI') {
                $name = 'Whats MAI';
            }
            try {
                list($user, $tenant) = app(CloudOnboardingService::class)->addCompany($user, [
                    'company_name' => $name,
                    'first_name' => $name,
                    'last_name' => $name,
                    'phone' => $user->phone,
                    'modules' => ['MESSAGING'],
                    'onboard_token' => (string) session('cloud_onboard_token'),
                    'account_kind' => 'personal',
                    'start_mode' => 'trial',
                    'mai_days' => 3,
                ]);
            } catch (\Exception $e) {
                return redirect()->route('whatsapp.mai.plan')->with('not_permitted', $e->getMessage());
            }
        }
        session([CloudTenantResolver::SESSION_KEY => $tenant->id]);

        return $this->finishFreeMai($request, $user, $tenant);
    }

    protected function finishFreeMai(Request $request, $user, $tenant)
    {
        $plan = \App\Cloud\CloudPlan::whereHas('module', function ($query) {
            $query->where('code', \App\Cloud\CloudModuleCode::MESSAGING);
        })->where('active', true)->first();
        $subscription = CloudSubscription::where('cloud_tenant_id', $tenant->id)->orderBy('id', 'desc')->first();
        if (! $subscription && $plan) {
            try {
                $subscription = app(\App\Services\Cloud\CloudSubscriptionService::class)->startTrial($tenant, $plan, $user->id);
            } catch (\Exception $e) {
                return redirect()->route('whatsapp.mai.plan')->with('not_permitted', $e->getMessage());
            }
        }
        if ($subscription) {
            $end = now()->addDays(3);
            $subscription->status = \App\Cloud\CloudSubscriptionStatus::TRIALING;
            if (! $subscription->trial_started_at) {
                $subscription->trial_started_at = now();
            }
            $subscription->trial_ends_at = $end;
            $subscription->current_period_start = now();
            $subscription->current_period_end = $end;
            $subscription->save();
        }
        if (Schema::hasTable('cloud_tenant_settings')) {
            \App\Cloud\CloudTenantSetting::updateOrCreate(
                ['cloud_tenant_id' => $tenant->id, 'key' => 'mai_days'],
                ['value' => '3', 'type' => 'string']
            );
            \App\Cloud\CloudTenantSetting::updateOrCreate(
                ['cloud_tenant_id' => $tenant->id, 'key' => 'mai_account'],
                ['value' => '1', 'type' => 'string']
            );
        }
        app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLink($user->phone ?: $tenant->phone);

        return redirect()->route('whatsapp.mai.home')->with('message', 'Your 3-day trial is free. The link on WhatsApp opens Whats MAI.');
    }

    protected function openMaiWhatsApp($phone)
    {
        try {
            app(\App\Services\Cloud\MaiWhatsAppEngine::class)->open($phone);
        } catch (\Exception $e) {
        }
    }

    protected function startMaiPayment(Request $request, $tenant)
    {
        $method = strtoupper((string) $request->input('payment_method'));
        if (! in_array($method, ['MTN', 'ORANGE', 'VISA'], true)) {
            return redirect()->route('whatsapp.mai.plan')->with('not_permitted', 'Choose MTN, Orange, or VISA.');
        }
        $subscriptions = CloudSubscription::where('cloud_tenant_id', $tenant->id)->get()->all();
        try {
            $payment = app(\App\Services\Cloud\CloudBillingCheckout::class)->open($tenant, $subscriptions, $method);
        } catch (\Exception $e) {
            return redirect()->route('whatsapp.mai.plan')->with('not_permitted', $e->getMessage());
        }
        $amount = (int) round((float) $payment->amount);
        $phone = preg_replace('/\D/', '', (string) $tenant->phone);
        if ($method === 'VISA') {
            $card = app(\App\Services\StripeCheckoutService::class)->checkout(
                'Whats MAI',
                $amount,
                route('whatsapp.mai.pay', ['payment' => $payment->id]).'?session_id={CHECKOUT_SESSION_ID}',
                route('whatsapp.mai.plan'),
                ['mai_payment' => (string) $payment->id]
            );
            if (empty($card['ok']) || empty($card['url'])) {
                $error = isset($card['error']) ? (string) $card['error'] : 'VISA could not be opened.';

                return redirect()->route('whatsapp.mai.plan')->with('not_permitted', $error);
            }
            $payment->provider = 'stripe';
            $payment->provider_reference = (string) $card['id'];
            $payment->status = \App\Cloud\CloudSubscriptionPayment::PENDING;
            $payment->save();

            return redirect()->away($card['url']);
        }
        $momo = app(\App\Services\CampayPayoutService::class)->momoNumber($phone);
        if (! $momo) {
            return redirect()->route('whatsapp.mai.plan')->with('not_permitted', 'MTN and Orange need a Cameroon number. Choose VISA for another country.');
        }
        $body = app(\App\Services\CampayPayoutService::class)->collect($momo, $amount, 'mai-'.$payment->id, 'Whats MAI');
        $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        if (! is_array($body) || empty($body['reference']) || $status === 'FAILED') {
            return redirect()->route('whatsapp.mai.plan')->with('not_permitted', 'Campay could not ask for the Mobile Money approval.');
        }
        $payment->provider = 'campay';
        $payment->provider_reference = (string) $body['reference'];
        $payment->status = \App\Cloud\CloudSubscriptionPayment::PENDING;
        $payment->save();

        return redirect()->route('whatsapp.mai.pay', ['payment' => $payment->id]);
    }

    protected function maiCustomerTenant($user)
    {
        if (! $user || ! Schema::hasTable('cloud_tenant_settings')) {
            return null;
        }
        $rows = \App\Cloud\CloudTenantMembership::where('user_id', $user->id)
            ->where('status', \App\Cloud\CloudMembershipStatus::ACTIVE)
            ->get();
        foreach ($rows as $row) {
            $flag = \App\Cloud\CloudTenantSetting::where('cloud_tenant_id', $row->cloud_tenant_id)
                ->where('key', 'mai_account')
                ->value('value');
            if ((string) $flag === '1') {
                return $row->cloudTenant;
            }
        }

        return null;
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
        $paid = app(CloudCheckoutService::class)->settle($payment->fresh());
        $payment = $payment->fresh();
        if (! $paid && $payment->method_code === 'CRYPTO') {
            $snap = json_decode((string) $payment->snapshot, true);
            $crypto = is_array($snap) && isset($snap['crypto']) ? $snap['crypto'] : [];

            return view('cloud.portal.crypto', [
                'tenant' => $tenant,
                'payment' => $payment,
                'crypto' => is_array($crypto) ? $crypto : [],
            ]);
        }

        return redirect()->route('cloud.home')->with(
            $paid ? 'message' : 'not_permitted',
            $paid ? 'Payment confirmed. This module is active.' : 'Payment is not confirmed yet. Finish it on your phone, then open this page again.'
        );
    }

    public function settings(Request $request)
    {
        $tenant = $this->tenant($request);
        if (! app()->runningUnitTests()) {
            return redirect()->route('setting.general');
        }

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
            'mai_days' => 'nullable|integer|in:3,7,15,30',
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
            $rules['username'] = [
                'nullable', 'string', 'min:3', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '' && app(\App\Services\Cloud\CloudSignupIdentity::class)->usernameTaken($value)) {
                        $fail('That username already exists. Choose another one.');
                    }
                },
            ];
        } elseif ($withAccount) {
            $rules['username'] = 'nullable|string|max:100';
        }
        if ($withAccount) {
            $rules['first_name'] = 'required|string|max:80';
            $rules['last_name'] = 'required|string|max:80';
            $rules['email'] = 'nullable|email|max:191';
            $rules['phone'] = 'required|string|max:32';
            $rules['password'] = 'nullable|string|max:191|confirmed';
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
