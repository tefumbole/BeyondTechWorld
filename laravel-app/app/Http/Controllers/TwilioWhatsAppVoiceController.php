<?php

namespace App\Http\Controllers;

use App\Services\Voice\TwilioRequestSignature;
use App\Services\Voice\TwilioWhatsAppTwiml;
use App\Services\Voice\TwilioWhatsAppVoiceProvider;
use App\Services\Voice\VoiceGateway;
use App\Services\Voice\VoiceScriptedTurn;
use App\Voice\VoiceCall;
use Illuminate\Http\Request;

class TwilioWhatsAppVoiceController extends Controller
{
    public function inbound(Request $request)
    {
        if (! app(TwilioRequestSignature::class)->valid($request)) {
            return response('Forbidden', 403);
        }

        $to = $this->whatsappNumber($request->input('To'));
        $from = $this->whatsappNumber($request->input('From'));
        if ($to === null || $from === null) {
            return $this->xml(app(TwilioWhatsAppTwiml::class)->reject());
        }

        $result = $this->gateway()->incoming($to, $from, $request->input('CallSid'), 'twilio-whatsapp');

        return $this->xml(app(TwilioWhatsAppTwiml::class)->forResult($result));
    }

    public function status(Request $request)
    {
        if (! app(TwilioRequestSignature::class)->valid($request)) {
            return response('Forbidden', 403);
        }

        $sid = (string) $request->input('CallSid');
        $state = strtolower((string) $request->input('CallStatus'));
        if ($sid !== '' && in_array($state, ['completed', 'canceled', 'busy', 'failed', 'no-answer'], true)) {
            $call = VoiceCall::where('provider_call_id', $sid)->orderBy('id')->first();
            if ($call && $call->status === 'ANSWERED') {
                $call->status = 'ENDED';
                $call->status_reason = $state === 'completed' ? 'caller_hangup' : $state;
                $call->ended_at = now();
                $call->recording = false;
                if ($call->answered_at) {
                    $call->duration_seconds = max(0, $call->answered_at->diffInSeconds($call->ended_at));
                }
                $call->save();
            }
        }

        return response('', 204);
    }

    protected function gateway()
    {
        return new VoiceGateway(new TwilioWhatsAppVoiceProvider(), app(VoiceScriptedTurn::class));
    }

    protected function whatsappNumber($value)
    {
        $value = trim((string) $value);
        if (stripos($value, 'whatsapp:') !== 0) {
            return null;
        }

        return substr($value, strlen('whatsapp:'));
    }

    protected function xml($body)
    {
        return response($body, 200)->header('Content-Type', 'text/xml');
    }
}
