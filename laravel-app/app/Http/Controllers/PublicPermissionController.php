<?php

namespace App\Http\Controllers;

use App\BeyondUser;
use App\Employee;
use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use App\StaffPermission;
use App\Support\CountryDialCodes;
use App\Support\WhatsAppMessage;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublicPermissionController extends Controller
{
    protected $auth;
    protected $whatsapp;

    public function __construct(BeyondAuthService $auth, BeyondWasenderService $whatsapp)
    {
        $this->auth = $auth;
        $this->whatsapp = $whatsapp;
    }

    public function index(Request $request)
    {
        $user = Auth::guard('beyond')->user();
        $otpOk = (bool) $request->session()->get('beyond_otp_verified');

        return view('beyond.permissions.index', [
            'user' => $user,
            'otpOk' => $otpOk,
            'countryCodes' => CountryDialCodes::all(),
            'draft' => $request->session()->get('permission_draft', []),
            'verifyStep' => (bool) $request->session()->get('permission_verify_phone'),
            'maskedPhone' => $request->session()->get('permission_verify_masked'),
        ]);
    }

    public function searchNames(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.$q.'%';
        $results = collect();

        BeyondUser::where('status', 'active')
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'email', 'phone', 'role'])
            ->each(function ($u) use ($results) {
                $results->push([
                    'id' => $u->id,
                    'source' => 'beyond',
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'phone_masked' => $u->phone ? $this->whatsapp->maskPhone($u->phone) : null,
                    'role' => $u->role,
                    'has_account' => true,
                ]);
            });

        if ($results->count() < 12) {
            Employee::where('is_active', true)
                ->where('name', 'like', $like)
                ->orderBy('name')
                ->limit(12 - $results->count())
                ->get(['id', 'name', 'email', 'phone_number'])
                ->each(function ($e) use ($results) {
                    $results->push([
                        'id' => 'emp:'.$e->id,
                        'source' => 'employee',
                        'name' => $e->name,
                        'email' => $e->email,
                        'phone' => $e->phone_number,
                        'phone_masked' => $e->phone_number ? $this->whatsapp->maskPhone($e->phone_number) : null,
                        'role' => 'employee',
                        'has_account' => $this->beyondAccountExists($e->email, $e->phone_number),
                    ]);
                });
        }

        if ($results->count() < 12) {
            User::where('is_active', 1)->where('is_deleted', 0)
                ->where('name', 'like', $like)
                ->orderBy('name')
                ->limit(12 - $results->count())
                ->get(['id', 'name', 'email', 'phone'])
                ->each(function ($u) use ($results) {
                    if ($results->contains('name', $u->name) && $results->contains('email', $u->email)) {
                        return;
                    }
                    $results->push([
                        'id' => 'pos:'.$u->id,
                        'source' => 'pos',
                        'name' => $u->name,
                        'email' => $u->email,
                        'phone' => $u->phone,
                        'phone_masked' => $u->phone ? $this->whatsapp->maskPhone($u->phone) : null,
                        'role' => 'staff',
                        'has_account' => $this->beyondAccountExists($u->email, $u->phone),
                    ]);
                });
        }

        return response()->json(['results' => $results->values()]);
    }

    protected function beyondAccountExists($email, $phone)
    {
        if ($email && BeyondUser::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
            return true;
        }
        if ($phone && $this->auth->findByPhone($phone)) {
            return true;
        }

        return false;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'company_role' => 'required|string|max:150',
            'from_at' => 'required|date',
            'to_at' => 'required|date|after:from_at',
            'reason' => 'required|string|max:3000',
            'password' => 'nullable|string|min:8|confirmed',
            'existing_user_id' => 'nullable|string|max:80',
        ]);

        $phone = CountryDialCodes::combine($data['country_code'], $data['phone']);
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return back()->withInput()->withErrors(['phone' => 'Enter a valid WhatsApp number for your country.']);
        }

        $existing = $this->resolveExistingUser($data['existing_user_id'] ?? null, $phone, $data['email'] ?? null);

        // Already signed in with OTP — submit immediately
        $sessionUser = Auth::guard('beyond')->user();
        if ($sessionUser && $request->session()->get('beyond_otp_verified')) {
            $permission = $this->createPermission($sessionUser, $data, $phone);
            $this->notifyPermission($permission);

            return redirect()->route('beyond.permissions.confirmation', $permission->reference_number);
        }

        if ($this->auth->shouldSkipOtp()) {
            $created = false;
            $tempPassword = null;
            if ($existing) {
                $user = $existing;
            } else {
                $created = true;
                list($user, $tempPassword) = $this->createPortalUser($data, $phone);
            }
            Auth::guard('beyond')->login($user);
            $request->session()->put('beyond_otp_verified', true);
            $permission = $this->createPermission($user, $data, $phone);
            $this->notifyPermission($permission, $created ? $tempPassword : null);

            return redirect()->route('beyond.permissions.confirmation', $permission->reference_number);
        }

        $draft = array_merge($data, ['phone_full' => $phone, 'existing_user_id' => $existing ? $existing->id : null]);
        $request->session()->put('permission_draft', $draft);

        $otp = $this->auth->createOtp($phone, 'permission_apply');
        $send = $this->whatsapp->sendOtp($phone, $otp['code'], 'permission');
        if (! ($send['success'] ?? false)) {
            return back()->withInput()->withErrors(['phone' => $send['error'] ?? 'Failed to send WhatsApp OTP.']);
        }

        $request->session()->put('permission_verify_phone', $otp['phone']);
        $request->session()->put('permission_verify_masked', $this->whatsapp->maskPhone($otp['phone']));

        return redirect()->route('beyond.permissions')->with('success', 'We sent a verification code to your WhatsApp. Enter it below to submit your permission request.');
    }

    public function verify(Request $request)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $phone = $request->session()->get('permission_verify_phone');
        $draft = $request->session()->get('permission_draft');
        if (! $phone || ! is_array($draft)) {
            return redirect()->route('beyond.permissions')->withErrors(['otp' => 'Session expired. Please submit the form again.']);
        }

        $result = $this->auth->verifyOtp($phone, $request->otp, 'permission_apply');
        if (! ($result['success'] ?? false)) {
            return redirect()->route('beyond.permissions')->withErrors(['otp' => $result['error'] ?? 'Invalid code.']);
        }

        $user = $this->resolveExistingUser($draft['existing_user_id'] ?? null, $phone, $draft['email'] ?? null);
        $created = false;
        $tempPassword = null;
        if (! $user) {
            $created = true;
            list($user, $tempPassword) = $this->createPortalUser($draft, $phone);
        } else {
            if (empty($user->phone)) {
                $user->phone = $phone;
                $user->save();
            }
            $this->auth->syncProfile($user);
        }

        Auth::guard('beyond')->login($user);
        $request->session()->put('beyond_otp_verified', true);
        $request->session()->put('beyond_masked_phone', $this->whatsapp->maskPhone($phone));

        $permission = $this->createPermission($user, $draft, $phone);
        $this->notifyPermission($permission, $created ? $tempPassword : null);

        $request->session()->forget(['permission_draft', 'permission_verify_phone', 'permission_verify_masked']);

        return redirect()->route('beyond.permissions.confirmation', $permission->reference_number)
            ->with('success', $created
                ? 'Account created and permission request submitted.'
                : 'Permission request submitted.');
    }

    public function resendOtp(Request $request)
    {
        $phone = $request->session()->get('permission_verify_phone');
        if (! $phone) {
            return redirect()->route('beyond.permissions')->withErrors(['otp' => 'Session expired. Submit the form again.']);
        }

        $otp = $this->auth->createOtp($phone, 'permission_apply');
        $send = $this->whatsapp->sendOtp($phone, $otp['code'], 'permission');
        if (! ($send['success'] ?? false)) {
            return back()->withErrors(['otp' => $send['error'] ?? 'Failed to resend code.']);
        }

        $request->session()->put('permission_verify_masked', $this->whatsapp->maskPhone($otp['phone']));

        return back()->with('success', 'A new verification code was sent to WhatsApp.');
    }

    public function confirmation($reference)
    {
        $permission = StaffPermission::where('reference_number', $reference)->first();

        return view('beyond.permissions.confirmation', compact('permission', 'reference'));
    }

    protected function resolveExistingUser($existingId, $phone, $email = null)
    {
        if ($existingId && strpos((string) $existingId, ':') === false) {
            $u = BeyondUser::where('id', $existingId)->where('status', 'active')->first();
            if ($u) {
                return $u;
            }
        }

        $byPhone = $this->auth->findByPhone($phone);
        if ($byPhone) {
            return $byPhone;
        }

        if ($email) {
            return BeyondUser::where('status', 'active')
                ->whereRaw('LOWER(email) = ?', [strtolower($email)])
                ->first();
        }

        return null;
    }

    protected function createPortalUser(array $data, $phone)
    {
        $password = ! empty($data['password'])
            ? $data['password']
            : Str::random(10);

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
        if ($email && BeyondUser::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
            $email = null;
        }
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
            'password_hash' => $this->auth->hashPassword($password),
            'role' => 'staff',
            'status' => 'active',
            'phone' => $phone,
            'must_change_credentials' => empty($data['password']),
        ]);
        $this->auth->syncProfile($user);

        return [$user, empty($data['password']) ? $password : null];
    }

    protected function createPermission(BeyondUser $user, array $data, $phone)
    {
        do {
            $ref = 'PERM-'.random_int(100000, 999999);
        } while (StaffPermission::where('reference_number', $ref)->exists());

        return StaffPermission::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => $data['full_name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'phone' => $phone,
            'company_role' => $data['company_role'],
            'from_at' => $data['from_at'],
            'to_at' => $data['to_at'],
            'reason' => $data['reason'],
            'status' => StaffPermission::STATUS_PENDING,
            'reference_number' => $ref,
        ]);
    }

    protected function notifyPermission(StaffPermission $permission, $tempPassword = null)
    {
        if (! $permission->phone) {
            return;
        }
        try {
            $msg = WhatsAppMessage::statusBlock('🗓️', 'Permission Request')
                .WhatsAppMessage::greeting($permission->full_name)
                ."Your permission request has been submitted and is awaiting approval.\n\n"
                .WhatsAppMessage::bullet('Reference', $permission->reference_number)
                .WhatsAppMessage::bullet('Role', $permission->company_role)
                .WhatsAppMessage::bullet('From', $permission->from_at->format('Y-m-d H:i'))
                .WhatsAppMessage::bullet('To', $permission->to_at->format('Y-m-d H:i'));
            if ($tempPassword) {
                $msg .= "\nYour portal account was created.\n"
                    .WhatsAppMessage::bullet('Username', optional(BeyondUser::find($permission->user_id))->username)
                    .WhatsAppMessage::bullet('Temporary password', $tempPassword)
                    ."\nPlease sign in and change your password.";
            }
            $msg .= WhatsAppMessage::footer();
            $this->whatsapp->sendText($permission->phone, $msg);
        } catch (\Throwable $e) {
            Log::warning('Permission request WhatsApp failed: '.$e->getMessage());
        }
    }
}
