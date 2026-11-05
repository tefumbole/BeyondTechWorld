<?php

namespace App\Travel;

use Illuminate\Support\Facades\DB;

class TravelAudit
{
    public static function write($tenantId, $userId, $action, $type, $id, array $payload = [])
    {
        DB::table('travel_audits')->insert([
            'cloud_tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => $id,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
