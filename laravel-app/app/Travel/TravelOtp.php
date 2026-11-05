<?php

namespace App\Travel;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TravelOtp
{
    public static function issue($tenantId, $phone, $purpose)
    {
        $phone = TravelPhone::normalize($phone);
        $recent = DB::table('travel_otps')
            ->where('cloud_tenant_id', $tenantId)
            ->where('phone', $phone)
            ->where('created_at', '>', now()->subMinute())
            ->count();
        if ($recent >= 3) {
            throw new TravelException('otp', 'Wait a minute before requesting another code.');
        }
        $code = (string) random_int(100000, 999999);
        DB::table('travel_otps')->insert([
            'cloud_tenant_id' => $tenantId,
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => self::hash($code),
            'expires_at' => now()->addMinutes((int) config('travel.otp_minutes', 10)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(TravelNotifier::class)->queue(
            $tenantId,
            $phone,
            "Epic Travels code\n\n*{$code}*\n\nThis code expires in ".(int) config('travel.otp_minutes', 10).' minutes. Do not share it.',
            'otp:'.$purpose.':'.$phone.':'.bin2hex(random_bytes(4))
        );
        if (app()->environment('testing')) {
            return $code;
        }

        return null;
    }

    public static function consume($tenantId, $phone, $purpose, $code)
    {
        $phone = TravelPhone::normalize($phone);
        $hash = self::hash(trim((string) $code));

        return DB::transaction(function () use ($tenantId, $phone, $purpose, $hash) {
            $row = DB::table('travel_otps')
                ->where('cloud_tenant_id', $tenantId)
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if (! $row || Carbon::now()->greaterThan(Carbon::parse($row->expires_at))) {
                return false;
            }
            if (! hash_equals($row->code_hash, $hash)) {
                return false;
            }
            $updated = DB::table('travel_otps')
                ->where('id', $row->id)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            return $updated === 1;
        });
    }

    public static function hash($code)
    {
        return hash_hmac('sha256', (string) $code, (string) config('app.key'));
    }
}
