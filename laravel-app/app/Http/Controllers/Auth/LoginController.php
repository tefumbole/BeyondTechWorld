<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\BeyondAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Unified login UI (staff/admin first, then Beyond customer).
     * Auth::routes registers GET /login here — must not redirect to /login (loop).
     */
    public function showLoginForm()
    {
        return app(BeyondAuthController::class)->showLogin(request());
    }

    /**
     * Unified login submit — accepts legacy `name` or new `identifier`.
     */
    public function login(Request $request)
    {
        if (! $request->filled('identifier') && $request->filled('name')) {
            $request->merge(['identifier' => $request->input('name')]);
        }

        return app(BeyondAuthController::class)->login($request);
    }

    public function sendOTP($phone)
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $result = app(\App\Services\Messaging\NotificationRouter::class)
            ->sendWhatsAppOtp($phone, $otp, 'login', 10);

        if (empty($result['success'])) {
            \Log::warning('[login-otp] send failed', [
                'phone' => $phone,
                'error' => $result['error'] ?? null,
                'provider' => $result['provider'] ?? null,
            ]);
            throw new \Exception($result['error'] ?? 'Failed to send WhatsApp OTP.');
        }

        return $otp;
    }

    /**
     * Admin/POS logout. Clears web OTP flag and any bridged Beyond portal session.
     * Portal users must POST to /portal/logout (beyond.logout) instead.
     */
    public function logout(Request $request)
    {
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->user()->update(['otp_verify' => '0']);
            Auth::guard('web')->logout();
        }
        if (Auth::guard('beyond')->check()) {
            Auth::guard('beyond')->logout();
        }
        $request->session()->forget(['beyond_otp_verified', 'beyond_masked_phone', 'password_reset_phone']);

        return redirect()->route('login');
    }
}
