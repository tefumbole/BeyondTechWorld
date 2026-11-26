<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Support\Facades\Schema;

/**
 * The provider comes from the stored connection, never from a request.
 */
class MaiProviderRouter
{
    const MAI = 'mai';

    public function usesMai(WhatsAppConversation $conversation)
    {
        if (! Schema::hasColumn($conversation->getTable(), 'mai_session_id')) {
            return false;
        }
        $id = (int) $conversation->mai_session_id;
        if ($id < 1) {
            return false;
        }
        $session = MaiSession::find($id);
        if (! $session || $session->provider !== self::MAI) {
            return false;
        }
        if (MaiBusinessLine::is($session->phone_number) || MaiBusinessLine::is($session->expected_phone)) {
            return false;
        }

        return true;
    }

    public function assertCustomerProvider($requested)
    {
        $requested = strtolower(trim((string) $requested));
        if ($requested !== '' && $requested !== self::MAI) {
            throw new \InvalidArgumentException('This connection uses Whats MAI. The provider cannot be changed.');
        }
    }
}
