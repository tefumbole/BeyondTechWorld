<?php

namespace App\Http\Controllers;

use App\BeyondProfile;
use App\BeyondUser;
use App\Http\Controllers\Auth\LoginController;
use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use App\Support\CountryDialCodes;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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

        // Already signed in as staff / admin
        if (Auth::guard('web')->check()) {
            $webUser = Auth::guard('web')->user();
            $role = $webUser ? Role::find($webUser->role_id) : null;
            $needsOtp = false;
            if ($role && (int) $role->id !== 5) {
                try {
                    $needsOtp = $role->hasPermissionTo('one_time_otp');
                } catch (\Throwable $e) {
                    $needsOtp = false;
                }
            }
            if ($needsOtp && (int) $webUser->otp_verify !== 1) {
                return redirect()->route('check.otp');
            }
            if ($role && (int) $role->id === 5 && (int) $webUser->otp_verify !== 1) {
                return redirect()->route('otp_screen');
            }

            return redirect('/admin');
        }

        if (Auth::guard('beyond')->check() && $request->session()->get('beyond_otp_verified')) {
            $user = Auth::guard('beyond')->user();
            $profile = BeyondProfile::find($user->id);

            return redirect($this->loginRedirect($request, $user, $profile));
        }

        $asCustomer = $request->get('as') === 'customer' || old('as') === 'customer';

        return view('beyond.auth.login', [
            'prefill' => $request->get('u', ''),
            'guestPassword' => $request->get('guest') === '1',
            'tab' => $request->get('tab') === 'signup' ? 'signup' : 'signin',
            'countryCodes' => CountryDialCodes::all(),
            'asCustomer' => $asCustomer,
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $phone = CountryDialCodes::combine($data['country_code'], $data['phone']);
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return back()->withInput()->withErrors(['phone' => 'Enter a valid WhatsApp number.']);
        }

        if ($this->auth->findByPhone($phone)) {
            return back()->withInput()->withErrors([
                'phone' => 'An account already exists with this phone. Sign in or reset your password via WhatsApp OTP.',
            ]);
        }

        if (! empty($data['email']) && BeyondUser::whereRaw('LOWER(email) = ?', [strtolower($data['email'])])->exists()) {
            return back()->withInput()->withErrors(['email' => 'Email is already registered. Sign in or reset your password.']);
        }

        $base = $this->auth->normalizeUsername(Str::slug($data['full_name'], '.') ?: 'user');
        if ($base === '') {
            $base = 'user';
        }
        $username = $base;
        $i = 1;
        while (BeyondUser::whereRaw('LOWER(username) = ?', [strtolower($username)])->exists()) {
            $username = $base.$i;
            $i++;
        }

        $email = $data['email'] ?? null;
        if (! $email) {
            $email = $username.'@beyond.local';
            $n = 1;
            while (BeyondUser::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
                $email = $username.$n.'@beyond.local';
                $n++;
            }
        }

        $user = BeyondUser::create([
            'id' => (string) Str::uuid(),
            'name' => $data['full_name'],
            'email' => $email,
            'username' => $username,
            'password_hash' => $this->auth->hashPassword($data['password']),
            'role' => 'staff',
            'status' => 'active',
            'phone' => $phone,
            'must_change_credentials' => false,
        ]);
        $this->auth->syncProfile($user);

        Auth::guard('beyond')->login($user);
        $request->session()->put('beyond_masked_phone', $this->whatsapp->maskPhone($phone));

        if ($this->auth->shouldSkipOtp()) {
            $request->session()->put('beyond_otp_verified', true);

            return redirect($this->loginRedirect($request, $user, BeyondProfile::find($user->id)));
        }

        $otp = $this->auth->createOtp($phone, 'login');
        $send = $this->whatsapp->sendOtp($phone, $otp['code']);
        if (! ($send['success'] ?? false)) {
            return back()->withInput()->withErrors(['phone' => $send['error'] ?? 'Failed to send WhatsApp OTP.']);
        }

        $request->session()->forget('beyond_otp_verified');

        return redirect('/otp-verification')->with('success', 'Account created. Enter the WhatsApp code to finish sign up.');
    }

    protected function postLoginRedirect(Request $request, $user, $profile)
    {
        $intended = $request->session()->pull('beyond_intended');
        if ($intended && strpos($intended, '/') === 0) {
            return $intended;
        }

        return $this->auth->redirectPath($user->role, $profile);
    }

    /**
     * Resolve the post-login destination. For admin-role Beyond users we also
     * sign them into the POS (web guard) so a single Beyond login + OTP lands
     * directly on the admin dashboard — no second login window.
     */
    protected function loginRedirect(Request $request, $user, $profile)
    {
        if ($this->bridgePosAdmin($user)) {
            $intended = $request->session()->pull('beyond_intended');
            if ($intended && strpos($intended, '/') === 0) {
                return $intended;
            }

            return '/admin';
        }

        return $this->postLoginRedirect($request, $user, $profile);
    }

    /**
     * Single sign-on bridge: if the Beyond user has an admin role and a matching
     * active POS account (by email) exists, authenticate the web guard too.
     */
    protected function bridgePosAdmin($user)
    {
        $adminRoles = ['admin', 'super_admin', 'director', 'manager'];
        if (! in_array(strtolower((string) $user->role), $adminRoles, true)) {
            return false;
        }

        $posUser = \App\User::where('email', $user->email)
            ->where('is_active', 1)
            ->where('is_deleted', 0)
            ->first();
        if (! $posUser) {
            return false;
        }

        $posUser->otp_verify = 1;
        $posUser->save();
        Auth::guard('web')->login($posUser, true);

        return true;
    }

    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
            'as' => 'nullable|in:customer,staff',
        ]);

        $identifier = trim($request->identifier);
        $password = $request->password;
        $forceCustomer = $request->input('as') === 'customer';

        // Unified login: staff/admin (users) first, then Beyond customer — unless forced customer.
        if (! $forceCustomer) {
            $staffResponse = $this->attemptStaffLogin($request, $identifier, $password);
            if ($staffResponse !== null) {
                return $staffResponse;
            }
        }

        $user = $this->auth->findByLogin($identifier);
        if (! $user || ! Hash::check($password, $user->password_hash)) {
            return back()->withInput()->withErrors(['identifier' => 'Invalid email/username or password.']);
        }

        $profile = BeyondProfile::find($user->id);
        $phone = optional($profile)->phone ?: $user->phone;
        if (! $phone || strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return back()->withInput()->withErrors(['identifier' => 'No valid phone number on this account. Contact support.']);
        }

        // Avoid mixed sessions
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        Auth::guard('beyond')->login($user);
        $request->session()->put('beyond_masked_phone', $this->whatsapp->maskPhone($phone));

        if ($this->auth->shouldSkipOtp()) {
            $request->session()->put('beyond_otp_verified', true);

            return redirect($this->loginRedirect($request, $user, $profile));
        }

        $otp = $this->auth->createOtp($phone, 'login');
        $send = $this->whatsapp->sendOtp($phone, $otp['code']);
        if (! ($send['success'] ?? false)) {
            return back()->withInput()->withErrors(['identifier' => $send['error'] ?? 'Failed to send WhatsApp OTP.']);
        }

        $request->session()->forget('beyond_otp_verified');

        return redirect('/otp-verification')->with('success', 'Verification code sent to your WhatsApp.');
    }

    /**
     * Try ERP staff/admin login (users table / web guard).
     * Returns a redirect response on handle, or null if no staff account exists for this identifier.
     * If a staff account exists but the password is wrong, returns an error (does not fall through to Beyond).
     */
    protected function attemptStaffLogin(Request $request, $identifier, $password)
    {
        $staff = $this->findStaffUser($identifier);
        if (! $staff) {
            return null;
        }

        $fieldType = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';
        $loginValue = $fieldType === 'email' ? $staff->email : $staff->name;
        if (! Auth::guard('web')->attempt([
            $fieldType => $loginValue,
            'password' => $password,
            'is_active' => 1,
        ])) {
            return back()->withInput()->withErrors(['identifier' => 'Invalid email/username or password.']);
        }

        if (Auth::guard('beyond')->check()) {
            Auth::guard('beyond')->logout();
        }
        $request->session()->forget(['beyond_otp_verified', 'beyond_masked_phone']);

        $role = Role::find(Auth::user()->role_id);
        if ($role && (int) $role->id !== 5) {
            $needsOtp = false;
            try {
                $needsOtp = $role->hasPermissionTo('one_time_otp');
            } catch (\Throwable $e) {
                $needsOtp = false;
            }
            if ($needsOtp) {
                Auth::user()->update(['otp_verify' => 0, 'otp' => null, 'otp_time' => null]);

                return redirect()->route('check.otp');
            }

            return redirect('/admin');
        }

        // ERP shop-customer role (legacy POS customer login)
        Auth::user()->update(['otp_verify' => 0]);
        try {
            $otp = app(LoginController::class)->sendOTP(Auth::user()->phone);
        } catch (\Throwable $e) {
            Auth::guard('web')->logout();

            return back()->withInput()->withErrors([
                'identifier' => 'Login succeeded but WhatsApp OTP failed: '.$e->getMessage(),
            ]);
        }
        Session::put('otp', $otp);

        return redirect()->route('otp_screen');
    }

    protected function findStaffUser($identifier)
    {
        $id = trim((string) $identifier);
        if ($id === '') {
            return null;
        }

        $query = User::query()
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->where('is_deleted', 0)
                    ->orWhere('is_deleted', false)
                    ->orWhereNull('is_deleted');
            });

        if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
            return (clone $query)->whereRaw('LOWER(email) = ?', [strtolower($id)])->first();
        }

        return (clone $query)->whereRaw('LOWER(name) = ?', [strtolower($id)])->first();
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

        return redirect($this->loginRedirect($request, $user, $profile));
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
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        $request->session()->forget(['beyond_otp_verified', 'beyond_masked_phone', 'password_reset_phone']);

        return redirect('/login');
    }

    public function showForgotPassword(Request $request)
    {
        return view('beyond.auth.forgot-password', [
            'prefillPhone' => $request->get('phone', ''),
            'countryCodes' => CountryDialCodes::all(),
        ]);
    }

    public function requestPasswordReset(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:40',
            'country_code' => 'nullable|string|max:10',
        ]);

        $phone = ! empty($data['country_code'])
            ? CountryDialCodes::combine($data['country_code'], $data['phone'])
            : $data['phone'];

        $user = $this->auth->findByPhone($phone);
        if (! $user) {
            return back()->withErrors(['phone' => 'No account found with this phone number.']);
        }

        $otp = $this->auth->createOtp($phone, 'password_reset');
        $send = $this->whatsapp->sendOtp($phone, $otp['code'], 'password_reset');
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
