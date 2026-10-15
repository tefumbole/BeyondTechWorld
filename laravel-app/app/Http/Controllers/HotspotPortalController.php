<?php

namespace App\Http\Controllers;

use App\Cloud\CloudTenant;
use App\Hotspot\HotspotAuditLog;
use App\Hotspot\HotspotBillingService;
use App\Hotspot\HotspotDevice;
use App\Hotspot\HotspotLimits;
use App\Hotspot\HotspotLocation;
use App\Hotspot\HotspotPackage;
use App\Hotspot\HotspotPayment;
use App\Hotspot\HotspotSubscription;
use App\Hotspot\HotspotVoucher;
use App\Services\Cloud\CloudTenantContext;
use Illuminate\Http\Request;

class HotspotPortalController extends Controller
{
    public function home(Request $request)
    {
        $location = $this->location($request);
        if (! $location) {
            return view('hotspot.portal', ['location' => null, 'packages' => collect(), 'support' => '237675321739']);
        }

        return view('hotspot.portal', $this->pageData($request, $location));
    }

    public function portal(Request $request)
    {
        $this->capture($request);
        $location = $this->location($request);
        if ($location) {
            $continued = $this->remembered($request, $location);
            if ($continued) {
                return $continued;
            }
        }

        return view('hotspot.portal', $this->pageData($request, $location));
    }

    public function pay(Request $request, HotspotBillingService $billing)
    {
        $location = $this->location($request);
        $package = $location ? HotspotPackage::where('active', 1)->find($request->input('package_id')) : null;
        if (! $location || ! $package) {
            return redirect()->route('hotspot.home')->with('hotspot_error', 'Choose a package that is still on sale.');
        }
        $request->validate([
            'name' => 'nullable|string|max:120',
            'phone' => 'required|string|max:30',
            'method' => 'required|in:momo,visa',
        ]);
        try {
            $customer = $billing->customer($this->guestName($request), $request->input('phone'));
            if ($request->input('method') === 'visa') {
                $started = $billing->startCard($package, $customer, $request->input('phone'), $location->id);

                return redirect()->away($started['url']);
            }
            $payment = $billing->startMoMo($package, $customer, $request->input('phone'), $location->id);
        } catch (\Throwable $e) {
            return redirect()->route('hotspot.portal')->with('hotspot_error', $e->getMessage());
        }

        return redirect()->route('hotspot.waiting', ['token' => $payment->public_token]);
    }

    public function waiting($token)
    {
        $payment = $this->payment($token);
        if (! $payment) {
            abort(404);
        }

        return view('hotspot.waiting', [
            'payment' => $payment,
            'statusUrl' => route('hotspot.status', ['token' => $payment->public_token]),
        ]);
    }

    public function status($token, HotspotBillingService $billing)
    {
        $payment = $this->payment($token);
        if (! $payment) {
            abort(404);
        }
        $state = $billing->settle($payment);
        $login = null;
        if ($state === 'paid') {
            $login = route('hotspot.login', ['token' => $payment->public_token]);
        }

        return response()->json(['state' => $state, 'login' => $login]);
    }

    public function resume(Request $request)
    {
        $location = $this->location($request);
        $tail = substr(preg_replace('/\D+/', '', (string) $request->input('phone')), -9);
        $payment = ($location && strlen($tail) >= 9)
            ? HotspotPayment::where('status', 'paid')->where('phone', 'like', '%'.$tail)->orderByDesc('id')->first()
            : null;
        $subscription = $payment ? HotspotSubscription::where('id', $payment->subscription_id)->where('state', 'active')->first() : null;
        if (! $payment || ! $subscription) {
            return redirect()->route('hotspot.home')->with('hotspot_error', 'No active access was found for that number.');
        }

        return redirect()->route('hotspot.login', ['token' => $payment->public_token]);
    }

    public function voucher(Request $request, HotspotBillingService $billing)
    {
        $location = $this->location($request);
        $request->validate([
            'code' => 'required|string|max:32',
            'name' => 'nullable|string|max:120',
            'phone' => 'required|string|max:30',
        ]);
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', $request->input('code')));
        $voucher = $location ? HotspotVoucher::where('code', $code)->first() : null;
        if (! $voucher) {
            HotspotAuditLog::write('voucher.guess', 'Unknown voucher attempt');

            return redirect()->route('hotspot.portal')->with('hotspot_error', 'That voucher was not found.');
        }
        if ($voucher->state === 'revoked') {
            return redirect()->route('hotspot.portal')->with('hotspot_error', 'This voucher has been revoked.');
        }
        if ($voucher->state !== 'available') {
            return redirect()->route('hotspot.portal')->with('hotspot_error', 'This voucher has already been used.');
        }
        try {
            $customer = $billing->customer($this->guestName($request), $request->input('phone'));
            $subscription = $billing->redeem($voucher, $customer, $location->id);
        } catch (\Throwable $e) {
            return redirect()->route('hotspot.portal')->with('hotspot_error', $e->getMessage());
        }
        $payment = HotspotPayment::where('subscription_id', $subscription->id)->first();
        $this->rememberDevice($request, $subscription);

        return redirect()->route('hotspot.login', ['token' => $payment->public_token])
            ->withCookie(cookie('btw_hotspot', $subscription->id, 60 * 24 * 30));
    }

    public function login(Request $request, $token)
    {
        $payment = $this->payment($token);
        $subscription = $payment ? HotspotSubscription::find($payment->subscription_id) : null;
        if (! $subscription || $subscription->state !== 'active') {
            return redirect()->route('hotspot.portal')->with('hotspot_error', 'This access is not active.')->withCookie(cookie()->forget('btw_hotspot'));
        }
        if (HotspotLimits::endReason($subscription->expires_at, now(), $subscription->data_used_bytes, $subscription->data_cap_bytes)) {
            app(\App\Hotspot\HotspotEnforcer::class)->close($subscription, HotspotLimits::endReason($subscription->expires_at, now(), $subscription->data_used_bytes, $subscription->data_cap_bytes));

            return redirect()->route('hotspot.portal')->with('hotspot_error', 'This access has expired.')->withCookie(cookie()->forget('btw_hotspot'));
        }
        $capture = session('hotspot_capture', []);
        $link = isset($capture['link-login']) ? $capture['link-login'] : '';
        if (! preg_match('#^https?://#i', $link)) {
            $link = '';
        }
        $this->rememberDevice($request, $subscription);

        return response(view('hotspot.login', [
            'subscription' => $subscription,
            'package' => $subscription->package,
            'link' => $link,
            'dst' => isset($capture['link-orig']) ? $capture['link-orig'] : '',
            'chapId' => isset($capture['chap-id']) ? $capture['chap-id'] : '',
            'chapChallenge' => isset($capture['chap-challenge']) ? $capture['chap-challenge'] : '',
            'password' => $this->chapPassword($subscription->access_secret, $capture),
            'support' => app(HotspotBillingService::class)->supportPhone(),
        ]))->withCookie(cookie('btw_hotspot', $subscription->id, 60 * 24 * 30));
    }

    protected function guestName(Request $request)
    {
        $name = trim((string) $request->input('name'));
        if ($name !== '') {
            return $name;
        }
        $phone = trim((string) $request->input('phone'));

        return $phone !== '' ? 'Wi-Fi '.$phone : 'Wi-Fi guest';
    }

    protected function pageData(Request $request, $location)
    {
        $packages = collect();
        if ($location) {
            $packages = HotspotPackage::where('active', 1)
                ->where(function ($query) use ($location) {
                    $query->whereNull('location_id')->orWhere('location_id', $location->id);
                })
                ->orderBy('price')
                ->orderBy('validity_seconds')
                ->get();
        }

        return [
            'location' => $location,
            'packages' => $packages,
            'support' => app(HotspotBillingService::class)->supportPhone(),
            'errorText' => session('hotspot_capture.error') ?: $request->input('error'),
        ];
    }

    protected function location(Request $request)
    {
        $requested = $request->input('location') ?: session('hotspot_location_id');
        $location = app(CloudTenantContext::class)->withoutIsolation(function () use ($requested) {
            if ($requested) {
                $found = HotspotLocation::where('active', 1)->where('id', $requested)->first();
                if ($found) {
                    return $found;
                }
            }

            return HotspotLocation::where('active', 1)->orderBy('id')->first();
        });
        if (! $location || ! $location->cloud_tenant_id) {
            return null;
        }
        $tenant = CloudTenant::find($location->cloud_tenant_id);
        if ($tenant) {
            app(CloudTenantContext::class)->set($tenant);
        }
        session(['hotspot_location_id' => $location->id]);

        return HotspotLocation::find($location->id);
    }

    protected function capture(Request $request)
    {
        $capture = session('hotspot_capture', []);
        foreach (['mac', 'ip', 'link-login', 'link-orig', 'error', 'chap-id', 'chap-challenge'] as $key) {
            if ($request->filled($key)) {
                $capture[$key] = $request->input($key);
            }
        }
        session(['hotspot_capture' => $capture]);
    }

    protected function remembered(Request $request, HotspotLocation $location)
    {
        $id = $request->cookie('btw_hotspot');
        if (! $id) {
            return null;
        }
        $subscription = HotspotSubscription::where('id', $id)->where('location_id', $location->id)->where('state', 'active')->first();
        if (! $subscription) {
            return null;
        }
        $mac = session('hotspot_capture.mac');
        if ($mac) {
            $known = HotspotDevice::where('subscription_id', $subscription->id)->where('mac', $mac)->exists();
            if (! $known) {
                return null;
            }
        }
        $payment = HotspotPayment::where('subscription_id', $subscription->id)->where('status', 'paid')->first();
        if (! $payment) {
            return null;
        }

        return redirect()->route('hotspot.login', ['token' => $payment->public_token]);
    }

    protected function rememberDevice(Request $request, HotspotSubscription $subscription)
    {
        $mac = session('hotspot_capture.mac');
        if (! $mac) {
            return;
        }
        $device = HotspotDevice::firstOrNew([
            'subscription_id' => $subscription->id,
            'mac' => $mac,
        ]);
        $device->last_seen_at = now();
        $device->save();
    }

    protected function payment($token)
    {
        $payment = app(CloudTenantContext::class)->withoutIsolation(function () use ($token) {
            return HotspotPayment::where('public_token', $token)->first();
        });
        if ($payment && $payment->cloud_tenant_id) {
            $tenant = CloudTenant::find($payment->cloud_tenant_id);
            if ($tenant) {
                app(CloudTenantContext::class)->set($tenant);
            }
        }

        return $payment ? HotspotPayment::find($payment->id) : null;
    }

    protected function chapPassword($password, array $capture)
    {
        $chapId = isset($capture['chap-id']) ? (string) $capture['chap-id'] : '';
        $challenge = isset($capture['chap-challenge']) ? (string) $capture['chap-challenge'] : '';
        if ($chapId === '' || $challenge === '' || $password === null) {
            return $password;
        }
        $idByte = strlen($chapId) <= 2 && ctype_xdigit($chapId) ? hex2bin(str_pad($chapId, 2, '0', STR_PAD_LEFT)) : $chapId;
        $chal = ctype_xdigit($challenge) && strlen($challenge) % 2 === 0 ? hex2bin($challenge) : $challenge;

        return md5($idByte.$password.$chal);
    }
}
