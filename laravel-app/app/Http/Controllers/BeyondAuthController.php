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
use Illuminate\Support\Facades\Schema;
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
            if ($role && (int) $role->id !== 5 && ! \App\Support\LocalDevAuth::skipStaffOtp()) {
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

            $internRedirect = \App\Support\InternCompliance::postLoginRedirect($webUser);
            if ($internRedirect) {
                return redirect($internRedirect);
            }

            $supervisorRedirect = \App\Support\InternCompliance::supervisorPostLoginRedirect($webUser);
            if ($supervisorRedirect) {
                return redirect($supervisorRedirect);
            }

            $intended = $request->session()->pull('beyond_intended');
            if ($intended && strpos($intended, '/') === 0 && strpos($intended, '//') !== 0) {
                return redirect($intended);
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

            $webUser = Auth::guard('web')->user();
            if ($webUser) {
                $supervisorRedirect = \App\Support\InternCompliance::supervisorPostLoginRedirect($webUser);
                if ($supervisorRedirect) {
                    return $supervisorRedirect;
                }
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
            $staff = $this->findStaffUser($identifier);
            if ($staff && Hash::check($password, $staff->password)) {
                return $this->completeStaffLogin($request, $staff);
            }
        }

        // A staff row matching the identifier must not shadow the portal account:
        // interns hold both, and only one of them may carry the new password.
        $user = $this->auth->findByLogin($identifier);
        if (! $user || ! Hash::check($password, $user->password_hash)) {
            \App\Services\ActivityLogService::log([
                'action' => 'failed_login',
                'entity' => 'auth',
                'user_name' => $identifier,
                'summary' => 'Failed login for '.$identifier,
                'method' => 'POST',
                'path' => '/login',
            ], $request);

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
     * Sign a verified ERP staff/intern account into the web guard and route them.
     *
     * The password is checked by the caller against the resolved row, so login by
     * email, username, phone or name all behave the same.
     */
    protected function completeStaffLogin(Request $request, User $staff)
    {
        Auth::guard('web')->login($staff);

        if (Auth::guard('beyond')->check()) {
            Auth::guard('beyond')->logout();
        }
        $request->session()->forget(['beyond_otp_verified', 'beyond_masked_phone']);

        $role = Role::find(Auth::user()->role_id);
        if ($role && (int) $role->id !== 5) {
            $needsOtp = false;
            if (! \App\Support\LocalDevAuth::skipStaffOtp()) {
                try {
                    $needsOtp = $role->hasPermissionTo('one_time_otp');
                } catch (\Throwable $e) {
                    $needsOtp = false;
                }
            }
            if ($needsOtp) {
                Auth::user()->update(['otp_verify' => 0, 'otp' => null, 'otp_time' => null]);
                \App\Services\ActivityLogService::log([
                    'action' => 'login',
                    'entity' => 'auth',
                    'summary' => 'Password OK — OTP required',
                    'method' => 'POST',
                    'path' => '/login',
                ], $request);

                return redirect()->route('check.otp');
            }

            Auth::user()->update(['otp_verify' => 1, 'otp' => null, 'otp_time' => null]);
            \App\Services\ActivityLogService::log([
                'action' => 'login',
                'entity' => 'auth',
                'summary' => \App\Support\LocalDevAuth::skipStaffOtp()
                    ? 'Logged in to admin (local OTP skipped)'
                    : 'Logged in to admin',
                'method' => 'POST',
                'path' => '/login',
            ], $request);

            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'must_set_password')
                && Auth::user()->must_set_password) {
                $request->session()->put('staff_must_set_password', true);

                return redirect('/staff-set-password');
            }

            $internRedirect = \App\Support\InternCompliance::postLoginRedirect(Auth::user());
            if ($internRedirect) {
                return redirect($internRedirect);
            }

            $supervisorRedirect = \App\Support\InternCompliance::supervisorPostLoginRedirect(Auth::user());
            if ($supervisorRedirect) {
                return redirect($supervisorRedirect);
            }

            $intended = $request->session()->pull('beyond_intended');
            if ($intended && strpos($intended, '/') === 0 && strpos($intended, '//') !== 0) {
                return redirect($intended);
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

        // Username chosen during account recovery, matched the way it was stored.
        if (Schema::hasColumn('users', 'username')) {
            $byUsername = (clone $query)
                ->whereRaw('LOWER(username) = ?', [strtolower($id)])
                ->first();
            if (! $byUsername) {
                $normalized = $this->auth->normalizeUsername($id);
                if ($normalized !== '' && $normalized !== strtolower($id)) {
                    $byUsername = (clone $query)
                        ->whereRaw('LOWER(username) = ?', [$normalized])
                        ->first();
                }
            }
            if ($byUsername) {
                return $byUsername;
            }
        }

        // Phone (digits) — admission letters tell interns to use WhatsApp number as username.
        $digits = preg_replace('/\D+/', '', $id);
        if (strlen($digits) >= 8) {
            $tail = substr($digits, -9);
            $byPhone = (clone $query)->where(function ($q) use ($id, $digits, $tail) {
                $q->where('phone', $id)
                    ->orWhere('phone', $digits)
                    ->orWhere('phone', '+'.$digits)
                    ->orWhereRaw(
                        "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                        [$tail]
                    );
            })->first();
            if ($byPhone) {
                return $byPhone;
            }
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

        $accounts = $this->resolveRecoverableAccountsByPhone($phone);
        if (! $accounts) {
            return back()->withErrors(['phone' => 'No account found with this phone number.']);
        }

        try {
            $formatted = $this->whatsapp->formatPhone($accounts[0]['phone'] ?: $phone);
        } catch (\Throwable $e) {
            return back()->withErrors(['phone' => 'Invalid WhatsApp number on this account.']);
        }

        $otp = $this->auth->createOtp($formatted, 'password_reset');
        $send = $this->whatsapp->sendOtp($formatted, $otp['code'], 'password_reset');
        if (empty($send['success'])) {
            return back()->withErrors(['phone' => $send['error'] ?? 'Failed to send verification code.']);
        }

        session([
            'password_reset_phone' => $otp['phone'],
            'password_reset_masked' => $this->whatsapp->maskPhone($otp['phone']),
            'password_reset_step' => 2,
            'password_reset_accounts' => array_map(function ($account) {
                return ['type' => $account['type'], 'id' => $account['id']];
            }, $accounts),
            'password_reset_current_username' => $accounts[0]['username'],
        ]);

        return redirect('/forgot-password')->with('success', 'Verification code sent to your WhatsApp.');
    }

    public function confirmPasswordReset(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
            'username' => 'required|string|min:3|max:100',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $phone = session('password_reset_phone');
        $accounts = session('password_reset_accounts', []);
        if (! $phone || ! is_array($accounts) || ! $accounts) {
            return redirect('/forgot-password')->withErrors(['otp' => 'Session expired. Request a new code.']);
        }

        $result = $this->auth->verifyOtp($phone, $request->otp, 'password_reset');
        if (! $result['success']) {
            return back()->withErrors(['otp' => $result['error']]);
        }

        $username = $this->auth->normalizeUsername($request->username);
        if (strlen($username) < 3) {
            return back()->withErrors([
                'username' => 'Use at least 3 letters or numbers. Letters, numbers, dots, dashes and underscores are kept; spaces become dots.',
            ]);
        }

        $ids = [];
        foreach ($accounts as $account) {
            $ids[$account['type']] = $account['id'];
        }

        if ($taken = $this->usernameTakenBy($username, $ids)) {
            return back()->withErrors(['username' => 'The username "'.$username.'" is already used by '.$taken.'. Choose another one.']);
        }

        $updated = 0;
        foreach ($accounts as $account) {
            if ($account['type'] === 'beyond') {
                $user = BeyondUser::find($account['id']);
                if (! $user) {
                    continue;
                }
                $user->username = $username;
                $user->password_hash = $this->auth->hashPassword($request->password);
                $user->must_change_credentials = false;
                $user->save();
                $this->auth->syncProfile($user);
                $updated++;

                continue;
            }

            $user = User::where('is_deleted', false)->where('is_active', 1)->find($account['id']);
            if (! $user) {
                continue;
            }
            // users.name stays as the person's real name — it is their payroll and
            // letter identity. The username lives in its own column.
            if (Schema::hasColumn('users', 'username')) {
                $user->username = $username;
            }
            $user->password = Hash::make($request->password);
            if (Schema::hasColumn('users', 'must_set_password')) {
                $user->must_set_password = 0;
            }
            $user->save();
            $updated++;
        }

        if (! $updated) {
            return back()->withErrors(['otp' => 'Account not found.']);
        }

        session()->forget([
            'password_reset_phone',
            'password_reset_masked',
            'password_reset_step',
            'password_reset_accounts',
            'password_reset_current_username',
        ]);

        return redirect('/forgot-password')
            ->with('reset_complete', true)
            ->with('reset_username', $username);
    }

    /**
     * Who else already answers to this username, across both login tables. The
     * ERP name column counts too, because signing in by name still works.
     *
     * @param  array{beyond?:string,web?:int}  $ownIds  accounts being updated
     * @return string|null description of the clashing account
     */
    protected function usernameTakenBy($username, array $ownIds)
    {
        $beyondClash = BeyondUser::whereRaw('LOWER(username) = ?', [$username])
            ->when(! empty($ownIds['beyond']), function ($q) use ($ownIds) {
                return $q->where('id', '!=', $ownIds['beyond']);
            })
            ->exists();
        if ($beyondClash) {
            return 'another portal account';
        }

        $staffQuery = User::where('is_deleted', false)
            ->where(function ($q) use ($username) {
                $q->whereRaw('LOWER(name) = ?', [$username]);
                if (Schema::hasColumn('users', 'username')) {
                    $q->orWhereRaw('LOWER(username) = ?', [$username]);
                }
            })
            ->when(! empty($ownIds['web']), function ($q) use ($ownIds) {
                return $q->where('id', '!=', $ownIds['web']);
            });

        return $staffQuery->exists() ? 'another staff account' : null;
    }

    /**
     * Every account reachable from one WhatsApp number: the Beyond portal login
     * and/or the ERP (intern/staff) login.
     *
     * A placed intern owns both, and login tries the ERP account first, so a
     * reset that touched only one of them left the person locked out with the
     * credentials they had just chosen.
     *
     * @return array<int, array{type:string,id:string|int,phone:string,username:string}>
     */
    protected function resolveRecoverableAccountsByPhone($phone)
    {
        $accounts = [];

        $beyond = $this->auth->findByPhone($phone);
        if ($beyond) {
            $accounts[] = [
                'type' => 'beyond',
                'id' => $beyond->id,
                'phone' => optional(BeyondProfile::find($beyond->id))->phone ?: $beyond->phone,
                'username' => $beyond->username ?: $beyond->email,
            ];
        }

        try {
            $formatted = $this->whatsapp->formatPhone($phone);
        } catch (\Throwable $e) {
            $formatted = preg_replace('/\D/', '', (string) $phone);
        }
        $digits = preg_replace('/\D/', '', (string) $formatted);
        if (strlen($digits) < 8) {
            return $accounts;
        }
        $tail = substr($digits, -9);

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
            $accounts[] = [
                'type' => 'web',
                'id' => $staff->id,
                'phone' => $staff->phone ?: $formatted,
                'username' => $staff->username ?: ($staff->email ?: $staff->name),
            ];
        }

        return $accounts;
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

        $message = 'Profile updated successfully.';
        if ($request->filled('username')) {
            $message .= ' Sign in with the username: '.$user->username;
        }

        return back()->with('success', $message);
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
