<?php

namespace App\Http\Controllers\Mai;

use App\Http\Controllers\Controller;
use App\Mai\MaiApiKey;
use App\Mai\MaiOutbound;
use App\Mai\MaiSession;
use App\Services\Mai\MaiCapabilities;
use App\Services\Mai\MaiLinkClient;
use App\Services\Mai\MaiOutboundService;
use App\Services\Mai\MaiSessionService;
use App\Services\Mai\MaiWebhookService;
use Illuminate\Http\Request;

class MaiApiController extends Controller
{
    public function sendMessage(Request $request)
    {
        $header = (string) $request->header('Authorization', '');
        $token = stripos($header, 'Bearer ') === 0 ? trim(substr($header, 7)) : '';
        if (! preg_match('/^[a-f0-9]{64}$/', $token) || ! \Illuminate\Support\Facades\Schema::hasColumn('mai_sessions', 'api_key')) {
            return response()->json(['success' => false, 'error' => 'Sign in with a session API key.'], 401);
        }
        $session = MaiSession::where('api_key', $token)->first();
        if (! $session || $session->status === 'UNLINKED') {
            return response()->json(['success' => false, 'error' => 'That session is not active.'], 401);
        }
        $to = trim((string) $request->input('to'));
        $text = trim((string) $request->input('text'));
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            $key = 'api-'.substr(sha1($token.'|'.$to.'|'.$text.'|'.date('YmdHi')), 0, 40);
        }
        $result = app(MaiOutboundService::class)->sendTo($session, $to, $text, $key);
        if (empty($result['success'])) {
            return response()->json([
                'success' => false,
                'error' => isset($result['error']) ? $result['error'] : 'The message was not sent.',
            ], ! empty($result['uncertain']) ? 202 : 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'msgId' => isset($result['msg_id']) ? $result['msg_id'] : null,
                'jid' => preg_replace('/\D+/', '', $to),
                'status' => 'in_progress',
            ],
        ]);
    }

    public function capabilities()
    {
        return response()->json(['ok' => true, 'capabilities' => MaiCapabilities::matrix()]);
    }

    public function createSession(Request $request)
    {
        try {
            $session = app(MaiSessionService::class)->open(
                $request->attributes->get('mai_user'),
                $request->input('phone'),
                $request->input('provider')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => 'Whats MAI is not open.'], 404);
        }
        $state = app(MaiLinkClient::class)->status($session->worker_session_id);

        return response()->json([
            'ok' => true,
            'session' => app(MaiSessionService::class)->present($session, is_array($state) ? (isset($state['qr']) ? $state['qr'] : null) : null),
        ]);
    }

    public function show(Request $request, $publicId)
    {
        $session = $this->owned($request, $publicId);
        if (! $session) {
            return response()->json(['ok' => false, 'error' => 'That session was not found.'], 404);
        }
        $fresh = app(MaiSessionService::class)->refresh($session);
        $state = app(MaiLinkClient::class)->status($fresh->worker_session_id);

        return response()->json([
            'ok' => true,
            'session' => app(MaiSessionService::class)->present($fresh, is_array($state) ? (isset($state['qr']) ? $state['qr'] : null) : null),
        ]);
    }

    public function pair(Request $request, $publicId)
    {
        $session = $this->owned($request, $publicId);
        if (! $session) {
            return response()->json(['ok' => false, 'error' => 'That session was not found.'], 404);
        }
        $fresh = app(MaiSessionService::class)->pair($session);

        return response()->json(['ok' => true, 'session' => app(MaiSessionService::class)->present($fresh)]);
    }

    public function disconnect(Request $request, $publicId)
    {
        $session = $this->owned($request, $publicId);
        if (! $session) {
            return response()->json(['ok' => false, 'error' => 'That session was not found.'], 404);
        }
        $fresh = app(MaiSessionService::class)->disconnect($session);

        return response()->json(['ok' => true, 'session' => app(MaiSessionService::class)->present($fresh)]);
    }

    public function send(Request $request, $publicId)
    {
        $session = $this->owned($request, $publicId);
        if (! $session) {
            return response()->json(['ok' => false, 'error' => 'That session was not found.'], 404);
        }
        $key = trim((string) $request->header('Idempotency-Key', $request->input('idempotency_key')));
        $apiKey = $request->attributes->get('mai_key');
        $type = strtolower(trim((string) $request->input('type', 'text')));
        if ($type === 'image' || $type === 'document') {
            $result = app(MaiOutboundService::class)->sendFile(
                $session,
                $request->input('to'),
                $type,
                $request->input('filename', $type),
                $request->input('mime', $type === 'image' ? 'image/jpeg' : 'application/pdf'),
                $request->input('data'),
                $key,
                $apiKey
            );
        } else {
            $result = app(MaiOutboundService::class)->sendTo($session, $request->input('to'), $request->input('text'), $key, $apiKey);
        }
        $status = ! empty($result['rate_limited']) ? 429 : (! empty($result['success']) ? 200 : (! empty($result['uncertain']) ? 202 : 422));

        return response()->json([
            'ok' => ! empty($result['success']),
            'status' => isset($result['status']) ? $result['status'] : null,
            'provider_message_id' => isset($result['msg_id']) ? $result['msg_id'] : null,
            'error' => isset($result['error']) ? $result['error'] : null,
        ], $status);
    }

    public function messageStatus(Request $request, $publicId, $idempotencyKey)
    {
        $session = $this->owned($request, $publicId);
        if (! $session) {
            return response()->json(['ok' => false, 'error' => 'That session was not found.'], 404);
        }
        $row = MaiOutbound::where('mai_session_id', $session->id)->where('idempotency_key', $idempotencyKey)->first();
        if (! $row) {
            return response()->json(['ok' => false, 'error' => 'That send was not found.'], 404);
        }

        $state = $row->status === 'pending' ? 'queued' : (string) $row->status;

        return response()->json([
            'ok' => true,
            'status' => $state,
            'provider_message_id' => $row->provider_message_id,
            'recipient_received' => false,
        ]);
    }

    public function webhook(Request $request)
    {
        try {
            $hook = app(MaiWebhookService::class)->register($request->attributes->get('mai_user'), $request->input('url'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'id' => $hook->id]);
    }

    public function revoke(Request $request, $id)
    {
        $user = $request->attributes->get('mai_user');
        $key = MaiApiKey::where('id', $id)->where('user_id', $user->id)->first();
        if (! $key) {
            return response()->json(['ok' => false, 'error' => 'That key was not found.'], 404);
        }
        app(MaiSessionService::class)->revokeKey($user, $key);

        return response()->json(['ok' => true]);
    }

    protected function owned(Request $request, $publicId)
    {
        $user = $request->attributes->get('mai_user');
        $session = MaiSession::where('public_id', $publicId)->first();
        if (! $session || ! $user || ! $session->allows($user)) {
            return null;
        }
        $key = $request->attributes->get('mai_key');
        if ($key && \Illuminate\Support\Facades\Schema::hasColumn('mai_api_keys', 'mai_session_id') && ! empty($key->mai_session_id) && (int) $key->mai_session_id !== (int) $session->id) {
            return null;
        }

        return $session;
    }
}
