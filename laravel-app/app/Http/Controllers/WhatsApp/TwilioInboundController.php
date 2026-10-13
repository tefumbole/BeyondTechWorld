<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\TwilioWhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TwilioInboundController extends Controller
{
    const NOTICE = 'For faster replies, please write to +237675321739.';

    public function handle(Request $request)
    {
        $service = app(TwilioWhatsAppService::class);
        if (! $this->signatureValid($request, $service->authToken())) {
            return response('Forbidden', 403);
        }

        $to = strtolower(trim((string) $request->input('To')));
        $from = trim((string) $request->input('From'));
        $ours = strtolower($service->fromNumber());
        if ($ours === '' || $to !== $ours || stripos($from, 'whatsapp:') !== 0) {
            return response('', 204);
        }

        $digits = preg_replace('/\D/', '', $from);
        $ownDigits = preg_replace('/\D/', '', $service->displayNumber());
        if ($digits === '' || $digits === $ownDigits || $digits === '237675321739') {
            return response('', 204);
        }

        $messageSid = trim((string) $request->input('MessageSid', $request->input('SmsSid')));
        if ($messageSid !== '' && ! Cache::add('twilio_inbound_sid_'.$messageSid, 1, now()->addDays(2))) {
            return response('', 204);
        }

        $body = trim((string) $request->input('Body'));
        $command = strtoupper(preg_replace('/\s+/', ' ', $body));
        $ownerCommand = in_array($command, ['AI ON', 'AI OFF', 'AI FIRST ON', 'AI FIRST OFF', 'AI STATUS'], true);
        try {
            $this->recordIncoming($digits, $messageSid, $body, $request);
        } catch (\Throwable $e) {
            if ($messageSid !== '') {
                Cache::forget('twilio_inbound_sid_'.$messageSid);
            }
            Log::warning('[twilio-whatsapp] inbound was not saved', [
                'tail' => substr($digits, -4),
                'error' => mb_substr($e->getMessage(), 0, 180),
            ]);
        }

        $aiOn = app(\App\Services\Assistant\AssistantPolicyService::class)->globallyEnabled();
        if ($ownerCommand || $aiOn) {
            return response('', 204);
        }

        $lockKey = 'twilio_fast_reply_'.$digits;
        if (! Cache::add($lockKey, 1, now()->addHours(24))) {
            return response('', 204);
        }

        $result = $service->sendText($digits, self::NOTICE);
        if (empty($result['success'])) {
            Cache::forget($lockKey);
            Log::warning('[twilio-whatsapp] fast-reply notice failed', [
                'tail' => substr($digits, -4),
                'error' => $this->safeError(isset($result['error']) ? $result['error'] : '', $service),
            ]);

            return response('', 204);
        }

        if ($messageSid !== '') {
            Cache::put('twilio_notice_sid_'.$messageSid, 1, now()->addDays(7));
        }
        Log::info('[twilio-whatsapp] fast-reply notice sent', ['tail' => substr($digits, -4)]);

        return response('', 204);
    }

    protected function recordIncoming($digits, $messageSid, $body, Request $request)
    {
        $parsed = [
            'phone' => $digits,
            'message_id' => $messageSid !== '' ? $messageSid : null,
            'body' => $body,
            'message_type' => ((int) $request->input('NumMedia') > 0 && $body === '') ? 'IMAGE' : 'TEXT',
            'wa_name' => trim((string) $request->input('ProfileName')),
        ];
        $save = function () use ($parsed) {
            return app(\App\Services\WhatsApp\WhatsAppConversationService::class)->recordIncoming($parsed);
        };
        $tenantId = app(\App\Services\Cloud\CloudWhatsAppConnectionResolver::class)->soleTenantId();
        if ($tenantId) {
            return app(\App\Services\Cloud\CloudTenantContextRunner::class)->run($tenantId, $save);
        }

        return $save();
    }

    protected function signatureValid(Request $request, $token)
    {
        $token = (string) $token;
        $given = (string) $request->header('X-Twilio-Signature');
        if ($token === '' || $given === '') {
            return false;
        }

        $params = $request->request->all();
        ksort($params);
        $payload = '';
        foreach ($params as $key => $value) {
            if (! is_array($value)) {
                $payload .= $key.$value;
            }
        }

        $query = $request->getQueryString();
        $suffix = $query ? '?'.$query : '';
        $urls = array_unique([
            'https://beyondtechworld.com/api/webhooks/twilio/whatsapp'.$suffix,
            $request->fullUrl(),
        ]);
        foreach ($urls as $url) {
            $expected = base64_encode(hash_hmac('sha1', $url.$payload, $token, true));
            if (hash_equals($expected, $given)) {
                return true;
            }
        }

        return false;
    }

    protected function safeError($error, TwilioWhatsAppService $service)
    {
        $error = str_replace([$service->accountSid(), $service->authToken()], '', (string) $error);

        return mb_substr(trim($error), 0, 180);
    }
}
