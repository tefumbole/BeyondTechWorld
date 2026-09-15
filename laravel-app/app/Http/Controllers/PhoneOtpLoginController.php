<?php

namespace App\Http\Controllers;

use App\BeyondProfile;
use App\BeyondUser;
use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use App\Services\PeopleDirectoryService;
use App\Support\CountryDialCodes;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * WhatsApp OTP login for anyone already in the system (task assignees, staff,
 * customers). A password is optional — phone + OTP is enough.
 */
class PhoneOtpLoginController extends Controller
{
    protected $auth;
    protected $whatsapp;

    public function __construct(BeyondAuthService $auth, BeyondWasenderService $whatsapp)
    {
        $this->auth = $auth;
        $this->whatsapp = $whatsapp;
    }

    public function show(Request $request)
    {
        \App\Support\AuthIntended::rememberFromRequest($request);

        if (Auth::guard('beyond')->check() && $request->session()->get('beyond_otp_verified')) {
            return redirect($this->afterLogin(Auth::guard('beyond')->user()));
        }

        $step = $request->session()->get('phone_otp_phone') ? 'otp' : 'phone';

        return view('beyond.auth.phone-otp-login', [
            'step' => $step,
            'countryCodes' => CountryDialCodes::all(),
            'maskedPhone' => $request->session()->get('phone_otp_masked'),
        ]);
    }

    public function requestOtp(Request $request)
    {
        \App\Support\AuthIntended::rememberFromRequest($request);
        $data = $request->validate([
            'phone' => 'required|string|max:40',
            'country_code' => 'nullable|string|max:10',
        ]);

        $phone = ! empty($data['country_code'])
            ? CountryDialCodes::combine($data['country_code'], $data['phone'])
            : $data['phone'];

        $user = $this->findPortalUserByPhone($phone);
        if (! $user) {
            return back()->withInput()->withErrors([
                'phone' => 'This number is not in the system. Use the WhatsApp number already on file, or ask an admin to add you first.',
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

        $otp = $this->auth->createOtp($formatted, 'phone_login');
        $send = $this->whatsapp->sendOtp($formatted, $otp['code'], 'login');
        if (empty($send['success'])) {
            return back()->withInput()->withErrors([
                'phone' => $send['error'] ?? 'Failed to send WhatsApp OTP.',
            ]);
        }

        $request->session()->put([
            'phone_otp_phone' => $otp['phone'],
            'phone_otp_user_id' => $user->id,
            'phone_otp_masked' => $this->whatsapp->maskPhone($otp['phone']),
        ]);

        return redirect()->route('phone.otp.login')->with('success', 'Verification code sent to '.$this->whatsapp->maskPhone($otp['phone']).'.');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $phone = $request->session()->get('phone_otp_phone');
        $userId = $request->session()->get('phone_otp_user_id');
        if (! $phone || ! $userId) {
            return redirect()->route('phone.otp.login')->withErrors(['otp' => 'Session expired. Request a new code.']);
        }

        $result = $this->auth->verifyOtp($phone, $request->otp, 'phone_login');
        if (empty($result['success'])) {
            return back()->withErrors(['otp' => $result['error'] ?? 'Invalid or expired verification code.']);
        }

        $user = BeyondUser::where('status', 'active')->find($userId);
        if (! $user) {
            return redirect()->route('phone.otp.login')->withErrors(['otp' => 'Account not found.']);
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        Auth::guard('beyond')->login($user);
        $request->session()->put('beyond_otp_verified', true);
        $request->session()->put('beyond_masked_phone', $this->whatsapp->maskPhone($phone));
        $request->session()->forget(['phone_otp_phone', 'phone_otp_user_id', 'phone_otp_masked']);

        return redirect($this->afterLogin($user))->with('status', 'Signed in with WhatsApp. You can add a password later if you want.');
    }

    public function resendOtp(Request $request)
    {
        $phone = $request->session()->get('phone_otp_phone');
        if (! $phone) {
            return redirect()->route('phone.otp.login');
        }

        $otp = $this->auth->createOtp($phone, 'phone_login');
        $send = $this->whatsapp->sendOtp($phone, $otp['code'], 'login');
        if (empty($send['success'])) {
            return back()->withErrors(['otp' => $send['error'] ?? 'Failed to resend code.']);
        }

        return back()->with('success', 'A new verification code was sent to '.$this->whatsapp->maskPhone($phone).'.');
    }

    protected function afterLogin(BeyondUser $user)
    {
        $intended = \App\Support\AuthIntended::pull();
        if ($intended) {
            return $intended;
        }

        return $this->auth->redirectPath($user->role, BeyondProfile::find($user->id));
    }

    protected function findPortalUserByPhone($phone)
    {
        try {
            $user = $this->auth->findByPhone($phone);
            if ($user) {
                return $user;
            }
        } catch (\Throwable $e) {
        }

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

        $profile = BeyondProfile::where(function ($q) use ($formatted, $digits, $tail) {
            $q->where('phone', $formatted)
                ->orWhere('phone', $digits)
                ->orWhere('phone', '+'.$digits)
                ->orWhereRaw(
                    "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                    [$tail]
                );
        })->first();
        if ($profile) {
            $user = BeyondUser::where('status', 'active')->find($profile->id);
            if ($user) {
                return $user;
            }
        }

        $directory = app(PeopleDirectoryService::class);

        $staff = User::where('is_deleted', false)
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
        if ($staff) {
            return $directory->ensureBeyondFromPosUser($staff);
        }

        $customer = $directory->findCustomerByLoosePhone($formatted);
        if ($customer) {
            return $directory->ensureBeyondFromCustomer($customer);
        }

        return null;
    }
}
