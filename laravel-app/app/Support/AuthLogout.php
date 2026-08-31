<?php

namespace App\Support;

use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * End every session (ERP + Beyond portal) so login cannot bounce the user back in.
 */
class AuthLogout
{
    public static function perform(Request $request)
    {
        if (Auth::guard('web')->check()) {
            try {
                ActivityLogService::log([
                    'action' => 'logout',
                    'entity' => 'auth',
                    'summary' => 'Logged out',
                    'method' => $request->method(),
                    'path' => '/'.$request->path(),
                ], $request);
                $user = Auth::guard('web')->user();
                if ($user && Schema::hasColumn('users', 'otp_verify')) {
                    $user->update(['otp_verify' => '0']);
                }
            } catch (\Throwable $e) {
                // Still sign the user out even if logging/OTP update fails.
            }
            Auth::guard('web')->logout();
        }

        if (Auth::guard('beyond')->check()) {
            Auth::guard('beyond')->logout();
        }

        $request->session()->forget([
            'beyond_otp_verified',
            'beyond_masked_phone',
            'password_reset_phone',
            'beyond_intended',
            'staff_must_set_password',
            'otp',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beyond.home');
    }
}
