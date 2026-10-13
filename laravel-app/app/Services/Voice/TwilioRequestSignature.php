<?php

namespace App\Services\Voice;

use Illuminate\Http\Request;

/**
 * Twilio signs the full URL plus the POST fields, sorted by name.
 * An empty auth token fails closed. The token is never logged.
 */
class TwilioRequestSignature
{
    public function valid(Request $request)
    {
        $token = (string) config('voice.twilio.auth_token');
        $given = (string) $request->header('X-Twilio-Signature');
        if ($token === '' || $given === '') {
            return false;
        }

        $params = $request->request->all();
        ksort($params);
        $data = $request->fullUrl();
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $data .= $key.$value;
        }
        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        return hash_equals($expected, $given);
    }
}
