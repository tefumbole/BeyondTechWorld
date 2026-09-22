<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWasenderWebhook;
use App\Services\WhatsApp\WaSenderEventParser;
use App\Services\WhatsApp\WaSenderSignature;
use App\WhatsApp\WhatsAppWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WaSenderWebhookController extends Controller
{
    public function __construct()
    {
        // Public webhook — skip the ERP permission share in the base controller.
    }

    public function info()
    {
        return response()->json([
            'ok' => true,
            'service' => 'wasender',
            'hint' => 'This endpoint accepts POST webhooks only.',
        ]);
    }

    public function handle(Request $request, WaSenderEventParser $parser)
    {
        $raw = $request->getContent();
        $header = $request->header('X-Webhook-Signature', $request->header('x-webhook-signature'));
        if (! WaSenderSignature::isValid($raw, $header)) {
            Log::warning('[whatsapp-hub] rejected webhook (signature)', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['ok' => false, 'error' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            $decoded = json_decode($raw, true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        if ($payload === []) {
            return response()->json(['ok' => false, 'error' => 'malformed payload'], 400);
        }

        $parsed = $parser->parse($payload);
        $fingerprint = $parser->fingerprint($payload, $raw);
        $existing = WhatsAppWebhookEvent::where('fingerprint', $fingerprint)->first();
        if ($existing) {
            return response()->json(['ok' => true, 'duplicate' => true, 'id' => $existing->id], 200);
        }

        try {
            $event = WhatsAppWebhookEvent::create([
                'provider' => 'wasender',
                'provider_event_id' => $parsed['provider_event_id'],
                'fingerprint' => $fingerprint,
                'event_type' => $parsed['type'] ?: 'unknown',
                'payload' => $raw !== '' ? $raw : json_encode($payload),
                'signature_verified' => true,
                'status' => WhatsAppWebhookEvent::QUEUED,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            $existing = WhatsAppWebhookEvent::where('fingerprint', $fingerprint)->first();

            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'id' => $existing ? $existing->id : null,
            ], 200);
        }

        ProcessWasenderWebhook::dispatch($event->id)->onQueue('whatsapp');

        return response()->json(['ok' => true, 'id' => $event->id], 200);
    }
}
