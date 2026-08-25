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
     * Admin/POS/intern logout. Clears both guards and destroys the session
     * so showLogin cannot bounce an intern back to their dashboard.
     */
    public function logout(Request $request)
    {
        return \App\Support\AuthLogout::perform($request);
    }
}
