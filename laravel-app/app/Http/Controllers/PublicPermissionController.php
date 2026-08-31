<?php

namespace App\Http\Controllers;

use App\BeyondUser;
use App\Customer;
use App\Services\BeyondAuthService;
use App\Services\BeyondWasenderService;
use App\Services\PeopleDirectoryService;
use App\StaffPermission;
use App\Support\CountryDialCodes;
use App\Support\WhatsAppMessage;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $draft = $request->session()->get('permission_draft', []);

        $countryCode = old('country_code', $draft['country_code'] ?? '+237');
        $phoneLocal = old('phone', $draft['phone'] ?? '');
        if ($user && $user->phone && $phoneLocal === '') {
            list($countryCode, $phoneLocal) = CountryDialCodes::split($user->phone);
        }

        return view('beyond.permissions.index', [
            'user' => $user,
            'otpOk' => $otpOk,
            'countries' => CountryDialCodes::list(),
            'countryCode' => $countryCode,
            'phoneLocal' => $phoneLocal,
            'draft' => $draft,
            'verifyStep' => (bool) $request->session()->get('permission_verify_phone'),
            'maskedPhone' => $request->session()->get('permission_verify_masked'),
        ]);
    }

    public function lookupAccount(Request $request)
    {
        $code = trim((string) $request->get('country_code', ''));
        $local = trim((string) $request->get('phone', ''));
        if ($code === '' || $local === '') {
            return response()->json(['found' => false]);
        }

        $phone = CountryDialCodes::combine($code, $local);
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return response()->json(['found' => false]);
        }

        $account = $this->findAccountByPhone($phone);
        if (! $account) {
            return response()->json([
                'found' => false,
                'message' => 'No account is linked to this WhatsApp number. Permission is only for existing accounts.',
            ]);
        }

        return response()->json([
            'found' => true,
            'id' => $account->id,
            'name' => $account->name,
            'role' => $account->role,
            'phone' => $account->phone,
            'phone_masked' => $this->whatsapp->maskPhone($account->phone),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'company_role' => 'required|string|max:150',
            'from_at' => 'required|date',
            'to_at' => 'required|date|after:from_at',
            'subject' => 'required|string|max:255',
            'reason' => 'required|string|max:3000',
            'existing_user_id' => 'nullable|string|max:80',
        ]);

        $phone = CountryDialCodes::combine($data['country_code'], $data['phone']);
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return back()->withInput()->withErrors(['phone' => 'Enter a valid WhatsApp number for your country.']);
        }

        $existing = $this->resolveExistingUser($data['existing_user_id'] ?? null, $phone);
        if (! $existing) {
            return back()->withInput()->withErrors([
                'phone' => 'No account is linked to this WhatsApp number. Permission is only for people who already have an account.',
            ]);
        }

        $data['full_name'] = $existing->name ?: $data['full_name'];
        $data['email'] = $existing->email;

        $sessionUser = Auth::guard('beyond')->user();
        if ($sessionUser && $request->session()->get('beyond_otp_verified')) {
            if ((string) $sessionUser->id !== (string) $existing->id) {
                Auth::guard('beyond')->login($existing);
            }
            $permission = $this->createPermission($existing, $data, $phone);
            $this->notifyPermission($permission);

            return redirect()->route('beyond.permissions.confirmation', $permission->reference_number);
        }

        if ($this->auth->shouldSkipOtp()) {
            Auth::guard('beyond')->login($existing);
            $request->session()->put('beyond_otp_verified', true);
            $permission = $this->createPermission($existing, $data, $phone);
            $this->notifyPermission($permission);

            return redirect()->route('beyond.permissions.confirmation', $permission->reference_number);
        }

        $draft = array_merge($data, ['phone_full' => $phone, 'existing_user_id' => $existing->id]);
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

        $user = $this->resolveExistingUser($draft['existing_user_id'] ?? null, $phone);
        if (! $user) {
            return redirect()->route('beyond.permissions')->withErrors([
                'otp' => 'No account is linked to this WhatsApp number. Permission is only for existing accounts.',
            ]);
        }

        if (empty($user->phone)) {
            $user->phone = $phone;
            $user->save();
        }
        $this->auth->syncProfile($user);

        Auth::guard('beyond')->login($user);
        $request->session()->put('beyond_otp_verified', true);
        $request->session()->put('beyond_masked_phone', $this->whatsapp->maskPhone($phone));

        $permission = $this->createPermission($user, $draft, $phone);
        $this->notifyPermission($permission);

        $request->session()->forget(['permission_draft', 'permission_verify_phone', 'permission_verify_masked']);

        return redirect()->route('beyond.permissions.confirmation', $permission->reference_number)
            ->with('success', 'Permission request submitted.');
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

    protected function resolveExistingUser($existingId, $phone)
    {
        if ($existingId && strpos((string) $existingId, ':') === false) {
            $u = BeyondUser::where('id', $existingId)->where('status', 'active')->first();
            if ($u) {
                return $u;
            }
        }

        return $this->findAccountByPhone($phone);
    }

    /**
     * Portal account matching this WhatsApp number: be_users, ERP staff, or customers.
     *
     * @return BeyondUser|null
     */
    protected function findAccountByPhone($phone)
    {
        $beyond = $this->auth->findByPhone($phone);
        if ($beyond) {
            return $beyond;
        }

        $directory = app(PeopleDirectoryService::class);

        $erp = $this->findErpUserByPhone($phone);
        if ($erp) {
            return $directory->ensureBeyondFromPosUser($erp);
        }

        $customer = $this->findCustomerByPhone($phone);
        if ($customer) {
            return $directory->ensureBeyondFromCustomer($customer);
        }

        return null;
    }

    protected function phoneLookupParts($phone)
    {
        try {
            $formatted = $this->whatsapp->formatPhone($phone);
        } catch (\Throwable $e) {
            $formatted = null;
        }
        $digits = preg_replace('/\D/', '', (string) ($formatted ?: $phone));
        $tail = substr($digits, -9);

        return [$formatted, $digits, $tail];
    }

    protected function wherePhoneColumn($q, $column, $formatted, $digits, $tail)
    {
        if ($formatted) {
            $q->orWhere($column, $formatted);
        }
        $q->orWhere($column, $digits)
            ->orWhere($column, '+'.$digits);
        if (strlen($tail) >= 8) {
            $q->orWhereRaw(
                "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(".$column.",''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                [$tail]
            );
        }
    }

    protected function findCustomerByPhone($phone)
    {
        list($formatted, $digits, $tail) = $this->phoneLookupParts($phone);
        if (strlen($digits) < 8) {
            return null;
        }

        return Customer::where(function ($q) use ($formatted, $digits, $tail) {
            $this->wherePhoneColumn($q, 'phone_number', $formatted, $digits, $tail);
        })
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();
    }

    protected function findErpUserByPhone($phone)
    {
        list($formatted, $digits, $tail) = $this->phoneLookupParts($phone);
        if (strlen($tail) < 8) {
            return null;
        }

        return User::where('is_active', 1)->where('is_deleted', 0)
            ->where(function ($q) use ($formatted, $digits, $tail) {
                $this->wherePhoneColumn($q, 'phone', $formatted, $digits, $tail);
                $this->wherePhoneColumn($q, 'additional_phone', $formatted, $digits, $tail);
            })
            ->orderBy('id')
            ->first();
    }

    protected function createPermission(BeyondUser $user, array $data, $phone)
    {
        do {
            $ref = 'PERM-'.random_int(100000, 999999);
        } while (StaffPermission::where('reference_number', $ref)->exists());

        return StaffPermission::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'full_name' => $data['full_name'] ?? $user->name,
            'email' => $data['email'] ?? $user->email,
            'phone' => $phone,
            'company_role' => $data['company_role'],
            'subject' => $data['subject'] ?? null,
            'from_at' => $data['from_at'],
            'to_at' => $data['to_at'],
            'reason' => $data['reason'],
            'status' => StaffPermission::STATUS_PENDING,
            'reference_number' => $ref,
        ]);
    }

    protected function notifyPermission(StaffPermission $permission)
    {
        if (! $permission->phone) {
            return;
        }
        try {
            $msg = WhatsAppMessage::statusBlock('🗓️', 'Permission Request')
                .WhatsAppMessage::greeting($permission->full_name)
                ."Your permission request has been submitted and is awaiting approval.\n\n"
                .WhatsAppMessage::bullet('Reference', $permission->reference_number)
                .WhatsAppMessage::bullet('Subject', $permission->subject ?: '—')
                .WhatsAppMessage::bullet('Role', $permission->company_role)
                .WhatsAppMessage::bullet('From', $permission->from_at->format('Y-m-d H:i'))
                .WhatsAppMessage::bullet('To', $permission->to_at->format('Y-m-d H:i'));
            $msg .= WhatsAppMessage::footer();
            $this->whatsapp->sendText($permission->phone, $msg);
        } catch (\Throwable $e) {
            Log::warning('Permission request WhatsApp failed: '.$e->getMessage());
        }

        $permissionId = $permission->id;
        app()->terminating(function () use ($permissionId) {
            try {
                $row = StaffPermission::find($permissionId);
                if ($row) {
                    app(\App\Services\StaffPermissionNotifier::class)->notifyAdminsOfNewRequest($row);
                }
            } catch (\Throwable $e) {
                Log::warning('Permission admin notify failed: '.$e->getMessage());
            }
        });
    }
}
