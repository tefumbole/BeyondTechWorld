<?php

namespace App\Services;

use App\Support\WhatsAppPhone;
use Twilio\Rest\Client;

class TwilioWhatsAppService
{
    public function isConfigured()
    {
        $sid = $this->accountSid();
        $token = $this->authToken();
        $from = $this->fromNumber();

        return $sid !== '' && $token !== '' && $from !== ''
            && strpos($sid, 'your_') !== 0;
    }

    public function accountSid()
    {
        return trim((string) (
            config('services.whatsapp.twilio_sid')
            ?: config('services.sms.account_sid')
            ?: env('TWILIO_SID')
            ?: env('ACCOUNT_SID')
        ));
    }

    public function authToken()
    {
        return trim((string) (
            config('services.whatsapp.twilio_token')
            ?: config('services.sms.auth_token')
            ?: env('TWILIO_AUTH_TOKEN')
            ?: env('AUTH_TOKEN')
        ));
    }

    public function fromNumber()
    {
        $from = trim((string) config('services.whatsapp.twilio_whatsapp_from', env('TWILIO_WHATSAPP_FROM', '')));
        if ($from === '') {
            return '';
        }

        if (stripos($from, 'whatsapp:') === 0) {
            return $from;
        }

        $digits = preg_replace('/\D/', '', $from);

        return $digits !== '' ? 'whatsapp:+'.$digits : $from;
    }

    /**
     * @param  string  $to
     * @param  string  $contentSid
     * @param  array<int|string,string>  $variables  keyed 1..n or "1".."n"
     * @param  string|null  $mediaUrl
     * @return array{success:bool,sid?:string,error?:string}
     */
    public function sendContentTemplate($to, $contentSid, array $variables = [], $mediaUrl = null)
    {
        $contentSid = trim((string) $contentSid);
        if ($contentSid === '') {
            return ['success' => false, 'error' => 'Twilio Content SID is missing.'];
        }

        if (! $this->isConfigured()) {
            \Log::warning('[twilio-whatsapp] not configured (SID/token/from)');

            return ['success' => false, 'error' => 'Twilio WhatsApp is not configured.'];
        }

        try {
            $recipient = $this->formatWhatsAppAddress($to);
            $payload = [
                'from' => $this->fromNumber(),
                'contentSid' => $contentSid,
            ];

            $vars = $this->normalizeVariables($variables);
            if (! empty($vars)) {
                $payload['contentVariables'] = json_encode($vars);
            }

            if (! empty($mediaUrl)) {
                $payload['mediaUrl'] = [$mediaUrl];
            }

            $client = new Client($this->accountSid(), $this->authToken());
            $message = $client->messages->create($recipient, $payload);

            return ['success' => true, 'sid' => $message->sid];
        } catch (\Throwable $e) {
            \Log::warning('[twilio-whatsapp] send failed', [
                'error' => $e->getMessage(),
                'content_sid' => $contentSid,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Free-form body (only works inside an open customer care session).
     *
     * @return array{success:bool,sid?:string,error?:string}
     */
    public function sendText($to, $body)
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Twilio WhatsApp is not configured.'];
        }

        try {
            $client = new Client($this->accountSid(), $this->authToken());
            $message = $client->messages->create($this->formatWhatsAppAddress($to), [
                'from' => $this->fromNumber(),
                'body' => (string) $body,
            ]);

            return ['success' => true, 'sid' => $message->sid];
        } catch (\Throwable $e) {
            \Log::warning('[twilio-whatsapp] free-form send failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function formatWhatsAppAddress($phone)
    {
        $normalized = WhatsAppPhone::normalize($phone);

        return 'whatsapp:+'.$normalized;
    }

    /**
     * @param  array<int|string,string>  $variables
     * @return array<string,string>
     */
    protected function normalizeVariables(array $variables)
    {
        $out = [];
        foreach ($variables as $key => $value) {
            $k = (string) $key;
            $v = trim((string) $value);
            if ($v === '') {
                $v = '-';
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
