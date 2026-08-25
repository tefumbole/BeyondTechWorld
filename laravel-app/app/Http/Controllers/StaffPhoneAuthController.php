<?php

namespace App\Http\Controllers;

use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use App\Support\CountryDialCodes;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Phone + WhatsApp OTP login for ERP staff (internship supervisors).
 * New supervisors set a password after first OTP verification.
 */
class StaffPhoneAuthController extends Controller
{
    protected $auth;
    protected $whatsapp;

    public function __construct(BeyondAuthService $auth, BeyondWasenderService $whatsapp)
    {
        $this->auth = $auth;
        $this->whatsapp = $whatsapp;
        $this->middleware('guest')->only(['show', 'requestOtp', 'verifyOtp', 'resendOtp']);
    }

    public function show(Request $request)
    {
        \App\Support\AuthIntended::rememberFromRequest($request);
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if ($this->userMustSetPassword($user) || $request->session()->get('staff_must_set_password')) {
                return redirect('/staff-set-password');
            }

            return $this->redirectAfterStaffLogin($user);
        }

        $step = $request->session()->get('staff_otp_phone') ? 'otp' : 'phone';

        return view('beyond.auth.staff-otp-login', [
            'step' => $step,
            'countryCodes' => CountryDialCodes::all(),
            'maskedPhone' => $request->session()->get('staff_otp_masked'),
        ]);
    }

    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:40',
            'country_code' => 'nullable|string|max:10',
        ]);

        $phone = ! empty($data['country_code'])
            ? CountryDialCodes::combine($data['country_code'], $data['phone'])
            : $data['phone'];

        $user = $this->findOrProvisionSupervisorByPhone($phone);
        if (! $user) {
            return back()->withInput()->withErrors([
                'phone' => 'No internship supervisor account found for this WhatsApp number. Ask an admin to assign you as a supervisor first.',
            ]);
        }

        try {
            $formatted = $this->whatsapp->formatPhone($user->phone ?: $phone);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['phone' => 'Invalid WhatsApp number on this account.']);
        }

        if ($user->phone !== $formatted) {
            $user->phone = $formatted;
            $user->save();
        }

        $otp = $this->auth->createOtp($formatted, 'staff_login');
        $send = $this->whatsapp->sendOtp($formatted, $otp['code'], 'login');
        if (empty($send['success'])) {
            return back()->withInput()->withErrors([
                'phone' => $send['error'] ?? 'Failed to send WhatsApp OTP.',
            ]);
        }

        $request->session()->put([
            'staff_otp_phone' => $otp['phone'],
            'staff_otp_user_id' => $user->id,
            'staff_otp_masked' => $this->whatsapp->maskPhone($otp['phone']),
            'staff_otp_step' => 'otp',
        ]);

        return redirect('/staff-otp-login')->with('success', 'Verification code sent to your WhatsApp.');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $phone = $request->session()->get('staff_otp_phone');
        $userId = (int) $request->session()->get('staff_otp_user_id');
        if (! $phone || ! $userId) {
            return redirect('/staff-otp-login')->withErrors(['otp' => 'Session expired. Request a new code.']);
        }

        $result = $this->auth->verifyOtp($phone, $request->otp, 'staff_login');
        if (empty($result['success'])) {
            return back()->withErrors(['otp' => $result['error'] ?? 'Invalid or expired verification code.']);
        }

        $user = User::where('is_deleted', false)->where('is_active', 1)->find($userId);
        if (! $user) {
            return redirect('/staff-otp-login')->withErrors(['otp' => 'Account not found.']);
        }

        if (Auth::guard('beyond')->check()) {
            Auth::guard('beyond')->logout();
        }

        Auth::guard('web')->login($user, true);
        $user->otp_verify = 1;
        $user->otp = null;
        $user->otp_time = null;
        $user->save();

        $request->session()->forget(['staff_otp_phone', 'staff_otp_user_id', 'staff_otp_masked', 'staff_otp_step']);
        $request->session()->forget(['beyond_otp_verified', 'beyond_masked_phone']);

        if ($this->userMustSetPassword($user)) {
            $request->session()->put('staff_must_set_password', true);

            return redirect('/staff-set-password');
        }

        return $this->redirectAfterStaffLogin($user);
    }

    public function resendOtp(Request $request)
    {
        $phone = $request->session()->get('staff_otp_phone');
        $userId = (int) $request->session()->get('staff_otp_user_id');
        if (! $phone || ! $userId) {
            return redirect('/staff-otp-login');
        }

        $otp = $this->auth->createOtp($phone, 'staff_login');
        $send = $this->whatsapp->sendOtp($phone, $otp['code'], 'login');
        if (empty($send['success'])) {
            return back()->withErrors(['otp' => $send['error'] ?? 'Failed to resend code.']);
        }

        return back()->with('success', 'A new verification code was sent.');
    }

    public function showSetPassword(Request $request)
    {
        if (! Auth::guard('web')->check()) {
            return redirect('/staff-otp-login');
        }
        if (! $request->session()->get('staff_must_set_password') && ! $this->userMustSetPassword(Auth::user())) {
            return $this->redirectAfterStaffLogin(Auth::user());
        }

        return view('beyond.auth.staff-set-password');
    }

    public function storeSetPassword(Request $request)
    {
        if (! Auth::guard('web')->check()) {
            return redirect('/staff-otp-login');
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();
        $user->password = Hash::make($request->password);
        if (Schema::hasColumn('users', 'must_set_password')) {
            $user->must_set_password = 0;
        }
        $user->save();
        $request->session()->forget('staff_must_set_password');

        return $this->redirectAfterStaffLogin($user)->with('success', 'Password saved. Welcome.');
    }

    /**
     * Find an existing ERP user by phone, or create one when this phone belongs to
     * a customer already listed as supervisor on an active internship enrolment.
     */
    protected function findOrProvisionSupervisorByPhone($phone)
    {
        $user = $this->findStaffByPhone($phone);
        if ($user) {
            try {
                app(\App\Services\ApplicationService::class)->ensureSupervisorAccess($user);
            } catch (\Throwable $e) {
            }

            return $user;
        }

        // Provision from customer directory when already assigned as supervisor
        try {
            $formatted = $this->whatsapp->formatPhone($phone);
        } catch (\Throwable $e) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $formatted);
        $tail = substr($digits, -9);
        if (strlen($tail) < 8) {
            return null;
        }

        $customer = \App\Customer::where(function ($q) use ($formatted, $digits, $tail) {
            $q->where('phone_number', $formatted)
                ->orWhere('phone_number', $digits)
                ->orWhere('phone_number', '+'.$digits)
                ->orWhereRaw(
                    "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone_number,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                    [$tail]
                );
        })->first();

        if (! $customer) {
            return null;
        }

        $ref = 'customer:'.$customer->id;
        $assigned = \App\InternshipEnrolment::whereIn('status', ['active', 'paused', 'pending'])
            ->where(function ($q) use ($ref) {
                $q->where('supervisors_json', 'like', '%'.$ref.'%');
            })
            ->exists();

        if (! $assigned) {
            return null;
        }

        $svc = app(\App\Services\ApplicationService::class);
        $bundle = $svc->resolveSupervisorSelection([$ref], null);
        $userId = $bundle['primary_user_id'] ?? null;

        return $userId ? User::find($userId) : null;
    }

    protected function findStaffByPhone($phone)
    {
        try {
            $formatted = $this->whatsapp->formatPhone($phone);
        } catch (\Throwable $e) {
            $formatted = preg_replace('/\D/', '', (string) $phone);
        }
        $digits = preg_replace('/\D/', '', (string) $formatted);
        if (strlen($digits) < 8) {
            return null;
        }
        $tail = substr($digits, -9);

        return User::where('is_deleted', false)
            ->where('is_active', 1)
            ->where(function ($q) use ($formatted, $digits, $tail) {
                $q->where('phone', $formatted)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', '+'.$digits)
                    ->orWhereRaw(
                        "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                        [$tail]
                    );
            })
            ->first();
    }

    protected function userMustSetPassword(User $user)
    {
        if (! Schema::hasColumn('users', 'must_set_password')) {
            return false;
        }

        return (bool) $user->must_set_password;
    }

    protected function redirectAfterStaffLogin(User $user)
    {
        return redirect(\App\Support\AuthIntended::afterLogin($user));
    }
}
