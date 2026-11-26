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
        $result = app(MaiOutboundService::class)->sendTo($session, $request->input('to'), $request->input('text'), $key);
        $status = ! empty($result['success']) ? 200 : (! empty($result['uncertain']) ? 202 : 422);

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

        return response()->json([
            'ok' => true,
            'status' => $row->status,
            'provider_message_id' => $row->provider_message_id,
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

        return $session;
    }
}
