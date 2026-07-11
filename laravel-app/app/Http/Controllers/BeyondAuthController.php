<?php

namespace App\Http\Controllers;

use App\BeyondProfile;
use App\BeyondUser;
use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BeyondAuthController extends Controller
{
    protected $auth;
    protected $whatsapp;

    public function __construct(BeyondAuthService $auth, BeyondWasenderService $whatsapp)
    {
        $this->auth = $auth;
        $this->whatsapp = $whatsapp;
    }

    public function showLogin(Request $request)
    {
        $redirect = $request->get('redirect');
        if ($redirect && strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0) {
            $request->session()->put('beyond_intended', $redirect);
        }

        if (Auth::guard('beyond')->check() && $request->session()->get('beyond_otp_verified')) {
            $user = Auth::guard('beyond')->user();
            $profile = BeyondProfile::find($user->id);

            return redirect($this->postLoginRedirect($request, $user, $profile));
        }

        return view('beyond.auth.login', [
            'prefill' => $request->get('u', ''),
            'guestPassword' => $request->get('guest') === '1',
        ]);
    }

    protected function postLoginRedirect(Request $request, $user, $profile)
    {
        $intended = $request->session()->pull('beyond_intended');
        if ($intended && strpos($intended, '/') === 0) {
            return $intended;
        }

        return $this->auth->redirectPath($user->role, $profile);
    }

    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = $this->auth->findByLogin($request->identifier);
        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            return back()->withInput()->withErrors(['identifier' => 'Invalid email/username or password.']);
        }

        $profile = BeyondProfile::find($user->id);
        $phone = optional($profile)->phone ?: $user->phone;
        if (! $phone || strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return back()->withInput()->withErrors(['identifier' => 'No valid phone number on this account. Contact support.']);
        }

        Auth::guard('beyond')->login($user);
        $request->session()->put('beyond_masked_phone', $this->whatsapp->maskPhone($phone));

        if ($this->auth->shouldSkipOtp()) {
            $request->session()->put('beyond_otp_verified', true);

            return redirect($this->postLoginRedirect($request, $user, $profile));
        }

        $otp = $this->auth->createOtp($phone, 'login');
        $send = $this->whatsapp->sendOtp($phone, $otp['code']);
        if (! $send['success']) {
            return back()->withInput()->withErrors(['identifier' => $send['error'] ?? 'Failed to send WhatsApp OTP.']);
        }

        $request->session()->forget('beyond_otp_verified');

        return redirect('/otp-verification')->with('success', 'Verification code sent to your WhatsApp.');
    }

    public function showOtp(Request $request)
    {
        if (! Auth::guard('beyond')->check()) {
            return redirect('/login');
        }
        if ($request->session()->get('beyond_otp_verified')) {
            $user = Auth::guard('beyond')->user();

            return redirect($this->auth->redirectPath($user->role, BeyondProfile::find($user->id)));
        }

        return view('beyond.auth.otp', [
            'maskedPhone' => $request->session()->get('beyond_masked_phone', 'your WhatsApp'),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => 'required|string|min:6|max:6']);

        $user = Auth::guard('beyond')->user();
        if (! $user) {
            return redirect('/login');
        }

        $phone = optional(BeyondProfile::find($user->id))->phone ?: $user->phone;
        $result = $this->auth->verifyOtp($phone, $request->otp, 'login');
        if (! $result['success']) {
            return back()->withErrors(['otp' => $result['error']]);
        }

        $this->auth->syncProfile($user);
        $request->session()->put('beyond_otp_verified', true);
        $profile = BeyondProfile::find($user->id);

        return redirect($this->postLoginRedirect($request, $user, $profile));
    }

    public function resendOtp(Request $request)
    {
        $user = Auth::guard('beyond')->user();
        if (! $user) {
            return redirect('/login');
        }

        $phone = optional(BeyondProfile::find($user->id))->phone ?: $user->phone;
        $otp = $this->auth->createOtp($phone, 'login');
        $send = $this->whatsapp->sendOtp($phone, $otp['code']);
        if (! $send['success']) {
            return back()->withErrors(['otp' => $send['error'] ?? 'Failed to resend code.']);
        }

        $request->session()->put('beyond_masked_phone', $this->whatsapp->maskPhone($phone));

        return back()->with('success', 'A new verification code was sent.');
    }

    public function logout(Request $request)
    {
        Auth::guard('beyond')->logout();
        $request->session()->forget(['beyond_otp_verified', 'beyond_masked_phone', 'password_reset_phone']);

        return redirect('/login');
    }

    public function showForgotPassword()
    {
        return view('beyond.auth.forgot-password');
    }

    public function requestPasswordReset(Request $request)
    {
        $request->validate(['phone' => 'required|string']);

        $user = $this->auth->findByPhone($request->phone);
        if (! $user) {
            return back()->withErrors(['phone' => 'No account found with this phone number.']);
        }

        $otp = $this->auth->createOtp($request->phone, 'password_reset');
        $send = $this->whatsapp->sendOtp($request->phone, $otp['code'], 'Password reset');
        if (! $send['success']) {
            return back()->withErrors(['phone' => $send['error'] ?? 'Failed to send verification code.']);
        }

        session([
            'password_reset_phone' => $otp['phone'],
            'password_reset_masked' => $this->whatsapp->maskPhone($otp['phone']),
            'password_reset_step' => 2,
        ]);

        return redirect('/forgot-password')->with('success', 'Verification code sent to your WhatsApp.');
    }

    public function confirmPasswordReset(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $phone = session('password_reset_phone');
        if (! $phone) {
            return redirect('/forgot-password')->withErrors(['otp' => 'Session expired. Request a new code.']);
        }

        $result = $this->auth->verifyOtp($phone, $request->otp, 'password_reset');
        if (! $result['success']) {
            return back()->withErrors(['otp' => $result['error']]);
        }

        $user = $this->auth->findByPhone($phone);
        if (! $user) {
            return back()->withErrors(['otp' => 'Account not found.']);
        }

        $user->password_hash = $this->auth->hashPassword($request->password);
        $user->save();
        session()->forget(['password_reset_phone', 'password_reset_masked', 'password_reset_step']);

        return redirect('/forgot-password')->with('reset_complete', true);
    }

    public function showProfile()
    {
        $user = Auth::guard('beyond')->user();
        $profile = BeyondProfile::find($user->id);

        return view('beyond.auth.profile', compact('user', 'profile'));
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::guard('beyond')->user();
        $request->validate([
            'full_name' => 'required|string|max:255',
            'username' => 'nullable|string|min:3|max:100',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        if ($request->filled('username')) {
            $norm = $this->auth->normalizeUsername($request->username);
            $exists = BeyondUser::whereRaw('LOWER(username) = ?', [$norm])
                ->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return back()->withErrors(['username' => 'Username is already taken.']);
            }
            $user->username = $norm;
        }

        if ($request->filled('email')) {
            $exists = BeyondUser::whereRaw('LOWER(email) = ?', [strtolower($request->email)])
                ->where('id', '!=', $user->id)->exists();
            if ($exists) {
                return back()->withErrors(['email' => 'Email is already in use.']);
            }
            $user->email = $request->email;
        }

        $user->name = $request->full_name;
        $user->address = $request->address;
        if ($request->filled('password')) {
            $user->password_hash = $this->auth->hashPassword($request->password);
        }
        $user->must_change_credentials = false;
        $user->save();
        $this->auth->syncProfile($user);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function showCompleteProfile()
    {
        $user = Auth::guard('beyond')->user();
        if (! $user || ! $user->must_change_credentials) {
            return redirect('/');
        }

        return view('beyond.auth.complete-profile', compact('user'));
    }

    public function completeProfile(Request $request)
    {
        $user = Auth::guard('beyond')->user();
        $request->validate([
            'full_name' => 'required|string|max:255',
            'username' => 'required|string|min:3|max:100',
            'email' => 'required|email|max:255',
            'address' => 'required|string|max:500',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $norm = $this->auth->normalizeUsername($request->username);
        if (BeyondUser::whereRaw('LOWER(username) = ?', [$norm])->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['username' => 'Username is already taken.']);
        }
        if (BeyondUser::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['email' => 'Email is already in use.']);
        }

        $user->fill([
            'name' => $request->full_name,
            'username' => $norm,
            'email' => $request->email,
            'address' => $request->address,
            'password_hash' => $this->auth->hashPassword($request->password),
            'must_change_credentials' => false,
        ])->save();
        $this->auth->syncProfile($user);

        return redirect($this->auth->redirectPath($user->role, BeyondProfile::find($user->id)));
    }
}
