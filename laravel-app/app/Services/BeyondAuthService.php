<?php

namespace App\Services;

use App\BeyondOtpSession;
use App\BeyondProfile;
use App\BeyondUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BeyondAuthService
{
    protected $whatsapp;

    public function __construct(BeyondWasenderService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function findByLogin($identifier)
    {
        $id = trim($identifier);
        if ($id === '') {
            return null;
        }

        return BeyondUser::where('status', 'active')
            ->where(function ($q) use ($id) {
                $q->whereRaw('LOWER(email) = ?', [strtolower($id)])
                    ->orWhereRaw('LOWER(username) = ?', [strtolower($id)]);
            })
            ->first();
    }

    public function findByPhone($phone)
    {
        $formatted = $this->whatsapp->formatPhone($phone);
        if (! $formatted) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $formatted);

        return BeyondUser::where('status', 'active')
            ->where(function ($q) use ($formatted, $digits) {
                $q->where('phone', $formatted)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''), '+', ''), ' ', ''), '-', ''), '(', '') = ?", [$digits]);
            })
            ->first();
    }

    public function syncProfile(BeyondUser $user)
    {
        BeyondProfile::updateOrCreate(
            ['id' => $user->id],
            [
                'email' => $user->email,
                'full_name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
                'username' => $user->username,
                'address' => $user->address,
                'must_change_credentials' => $user->must_change_credentials,
                'status' => $user->status,
            ]
        );
    }

    public function createOtp($phone, $purpose = 'login')
    {
        $formatted = $this->whatsapp->formatPhone($phone);
        $code = (string) random_int(100000, 999999);

        BeyondOtpSession::create([
            'id' => (string) Str::uuid(),
            'phone' => $formatted,
            'otp' => $code,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'resend_count' => 0,
            'purpose' => $purpose,
            'created_at' => now(),
        ]);

        return ['code' => $code, 'phone' => $formatted];
    }

    public function verifyOtp($phone, $otp, $purpose = 'login')
    {
        $formatted = $this->whatsapp->formatPhone($phone);
        $clean = preg_replace('/\D/', '', $otp);

        $session = BeyondOtpSession::where('phone', $formatted)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>=', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $session || $session->otp !== $clean) {
            if ($session) {
                $session->increment('attempts');
            }

            return ['success' => false, 'error' => 'Invalid or expired verification code.'];
        }

        $session->verified_at = now();
        $session->save();

        return ['success' => true];
    }

    public function redirectPath($role, $profile = null)
    {
        if ($profile && ($profile->must_change_credentials || optional($profile)->must_change_credentials)) {
            return '/complete-profile';
        }

        $r = strtolower((string) $role);
        $adminRoles = ['admin', 'super_admin', 'director', 'manager'];
        if (in_array($r, $adminRoles, true)) {
            return '/dashboard';
        }
        if (in_array($r, ['task_assignee', 'customer'], true)) {
            return '/user/tasks/pending-acceptances';
        }
        if (in_array($r, ['staff', 'employee', 'teacher'], true)) {
            return '/user/tasks';
        }
        if ($r === 'student') {
            return '/student/dashboard';
        }
        if ($r === 'shareholder') {
            return '/shareholders';
        }
        if ($r === 'applicant') {
            return '/applicant/dashboard';
        }

        return '/';
    }

    public function shouldSkipOtp()
    {
        return app()->environment('local') && env('BEYOND_SKIP_OTP', false);
    }

    public function hashPassword($password)
    {
        return Hash::make($password);
    }

    public function normalizeUsername($value)
    {
        return strtolower(preg_replace('/[^a-z0-9._-]/', '', trim($value)));
    }
}
