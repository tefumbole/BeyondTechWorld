<?php

namespace App\Services\Property;

use App\Property\PropertyActivity;

class PropertyActivityLogger
{
    public function write($action, $type, $id, array $meta = [], $conversationId = null)
    {
        foreach (['otp', 'otp_code', 'secret', 'token', 'password', 'api_token', 'webhook_secret'] as $key) {
            unset($meta[$key]);
        }
        if (isset($meta['account_reference'])) {
            $ref = (string) $meta['account_reference'];
            $meta['account_tail'] = strlen($ref) > 4 ? substr($ref, -4) : 'set';
            unset($meta['account_reference']);
        }
        PropertyActivity::create([
            'action' => substr((string) $action, 0, 60),
            'subject_type' => $type,
            'subject_id' => $id,
            'conversation_id' => $conversationId,
            'meta' => json_encode($meta),
        ]);
    }
}
