<?php

namespace App\Travel;

use App\Jobs\TravelFlushOutbox;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TravelNotifier
{
    public function queue($tenantId, $phone, $body, $dedupe)
    {
        try {
            $id = DB::table('travel_outbox')->insertGetId([
                'cloud_tenant_id' => $tenantId,
                'phone' => $phone,
                'body' => $body,
                'dedupe_key' => $dedupe,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            return null;
        }
        TravelFlushOutbox::dispatch($id);

        return $id;
    }

    public function deliver($id)
    {
        $row = DB::table('travel_outbox')->where('id', $id)->first();
        if (! $row || $row->status === 'sent') {
            return;
        }
        if (! config('travel.whatsapp_live')) {
            DB::table('travel_outbox')->where('id', $id)->update([
                'status' => 'sent',
                'provider' => 'fake',
                'updated_at' => now(),
            ]);

            return;
        }
        try {
            app(\App\Services\Messaging\NotificationRouter::class)
                ->sendWhatsAppTextWithLink($row->phone, $row->body);
            DB::table('travel_outbox')->where('id', $id)->update([
                'status' => 'sent',
                'provider' => 'wasender',
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('travel_outbox')->where('id', $id)->update([
                'status' => 'failed',
                'provider' => 'wasender',
                'attempts' => (int) $row->attempts + 1,
                'updated_at' => now(),
            ]);
        }
    }
}
