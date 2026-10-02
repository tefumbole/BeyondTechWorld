<?php

namespace App\Services\Cloud;

use App\Cloud\CloudWhatsAppConnection;

/**
 * In-process WhatsApp session double. It never calls WaSender.
 */
class CloudStubWhatsAppSessionProvider
{
    public function provision(CloudWhatsAppConnection $connection)
    {
        if (config('services.whatsapp.stub_fail')) {
            return [];
        }

        return [
            'session_id' => 'stub-'.$connection->id,
            'status' => 'AWAITING_QR',
        ];
    }

    public function qr(CloudWhatsAppConnection $connection)
    {
        return 'stub-qr-'.$connection->id;
    }

    public function status(CloudWhatsAppConnection $connection)
    {
        $forced = config('services.whatsapp.stub_status');

        return $forced ? (string) $forced : 'AWAITING_QR';
    }

    public function disconnect(CloudWhatsAppConnection $connection)
    {
        return true;
    }
}
