<?php

namespace App\Http\Controllers\Mai;

use App\Http\Controllers\Controller;
use App\Mai\MaiSession;
use App\Services\Mai\MaiCapabilities;
use App\Services\Mai\MaiCustomerAuth;
use App\Services\Mai\MaiLinkClient;
use App\Services\Mai\MaiOutboundService;
use App\Services\Mai\MaiSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaiPortalController extends Controller
{
    public function home(Request $request)
    {
        if (\App\Support\MaiHost::is($request)) {
            return app(\App\Http\Controllers\CloudPortalController::class)->whatsappMai($request);
        }
        if (! config('cloud.mai_customer.enabled')) {
            return response()->view('mai.closed', [], 404);
        }
        if (! Auth::check() || ! app(MaiCustomerAuth::class)->verified(Auth::user())) {
            return view('mai.guest');
        }
        $sessions = MaiSession::where('user_id', Auth::id())->orderByDesc('id')->get();
        $keys = \App\Mai\MaiApiKey::where('user_id', Auth::id())->orderByDesc('id')->get();

        return view('mai.home', [
            'sessions' => $sessions,
            'keys' => $keys,
            'capabilities' => MaiCapabilities::matrix(),
            'apiBase' => url('/api/mai/v1'),
        ]);
    }

    public function register(Request $request)
    {
        try {
            app(MaiCustomerAuth::class)->register($request->input('name'), $request->input('email'), $request->input('password'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->to('/mai')->with('not_permitted', $e->getMessage());
        } catch (\RuntimeException $e) {
            return response()->view('mai.closed', [], 404);
        }

        return redirect()->to('/mai')->with('message', 'Check your email and confirm it before linking WhatsApp.');
    }

    public function login(Request $request)
    {
        try {
            app(MaiCustomerAuth::class)->login($request->input('email'), $request->input('password'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->to('/mai')->with('not_permitted', $e->getMessage());
        } catch (\RuntimeException $e) {
            return response()->view('mai.closed', [], 404);
        }

        return redirect()->to('/mai');
    }

    public function verify($token)
    {
        try {
            app(MaiCustomerAuth::class)->confirm($token);
        } catch (\Exception $e) {
            return redirect()->to('/mai')->with('not_permitted', $e->getMessage());
        }

        return redirect()->to('/mai')->with('message', 'Email confirmed. You can link WhatsApp now.');
    }

    public function logout()
    {
        Auth::logout();

        return redirect()->to('/mai');
    }

    public function createSession(Request $request)
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to('/mai');
        }
        try {
            $session = app(MaiSessionService::class)->open($user, $request->input('phone'), $request->input('provider'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->to('/mai')->with('not_permitted', $e->getMessage());
        } catch (\RuntimeException $e) {
            return response()->view('mai.closed', [], 404);
        }
        $state = app(MaiLinkClient::class)->status($session->worker_session_id);

        return redirect()->to('/mai')->with('message', 'Scan the QR code on this page.')->with('mai_qr', is_array($state) ? (isset($state['qr']) ? $state['qr'] : '') : '');
    }

    public function pair($publicId)
    {
        $session = $this->owned($publicId);
        if (! $session) {
            return redirect()->to('/mai');
        }
        return redirect()->to('/mai')->with('message', 'Scan the QR code. Linking with a phone number is not available.');
    }

    public function refresh($publicId)
    {
        $session = $this->owned($publicId);
        if (! $session) {
            return redirect()->to('/mai');
        }
        app(MaiSessionService::class)->refresh($session);

        return redirect()->to('/mai');
    }

    public function rename(Request $request, $publicId)
    {
        $session = $this->managed($publicId);
        if (! $session) {
            return redirect()->to('/mai');
        }
        $name = trim((string) $request->input('name'));
        if ($name !== '') {
            $session->display_name = substr($name, 0, 191);
            $session->save();
        }

        return redirect()->to('/mai')->with('message', 'Session name saved.');
    }

    public function disconnect(Request $request, $publicId)
    {
        $session = $this->managed($publicId);
        if (! $session) {
            return redirect()->to('/mai');
        }
        if ($request->input('confirm') !== 'yes') {
            return redirect()->to('/mai')->with('not_permitted', 'Confirm before disconnecting this session.');
        }
        app(MaiSessionService::class)->disconnect($session);

        return redirect()->to('/mai')->with('message', 'This phone was unlinked. The chat history is still here. Link it again to send.');
    }

    public function send(Request $request, $publicId)
    {
        $session = $this->owned($publicId);
        if (! $session) {
            return redirect()->to('/mai');
        }
        $key = trim((string) $request->input('idempotency_key'));
        if ($key === '') {
            $key = 'portal-'.bin2hex(random_bytes(8));
        }
        $result = app(MaiOutboundService::class)->sendTo($session, $request->input('to'), $request->input('text'), $key);
        if (! empty($result['uncertain'])) {
            return redirect()->to('/mai')->with('not_permitted', 'Delivery was not confirmed. It was not sent again.');
        }
        if (empty($result['success'])) {
            return redirect()->to('/mai')->with('not_permitted', isset($result['error']) ? $result['error'] : 'The message was not sent.');
        }

        return redirect()->to('/mai')->with('message', 'Sent.');
    }

    public function createKey(Request $request)
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to('/mai');
        }
        $session = null;
        if ($request->filled('session')) {
            $session = MaiSession::where('public_id', $request->input('session'))->first();
            if (! $session || ! $session->canManage($user)) {
                return redirect()->to('/mai')->with('not_permitted', 'Choose a session you can manage.');
            }
        }
        try {
            $issued = app(MaiSessionService::class)->issueKey($user, $request->input('name'), $session, $request->input('scopes'));
        } catch (\RuntimeException $e) {
            return response()->view('mai.closed', [], 404);
        }

        return redirect()->to('/mai')->with('mai_key', $issued['plain'])->with('message', 'Copy this API token now. It is shown once.');
    }

    public function rotateKey($id)
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to('/mai');
        }
        $key = \App\Mai\MaiApiKey::where('id', $id)->where('user_id', $user->id)->first();
        if (! $key) {
            return redirect()->to('/mai');
        }
        $issued = app(MaiSessionService::class)->rotateKey($user, $key);
        if (! $issued) {
            return redirect()->to('/mai')->with('not_permitted', 'That token is already revoked.');
        }

        return redirect()->to('/mai')->with('mai_key', $issued['plain'])->with('message', 'The new token is shown once. The previous token no longer works.');
    }

    public function revokeKey($id)
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to('/mai');
        }
        $key = \App\Mai\MaiApiKey::where('id', $id)->where('user_id', $user->id)->first();
        if ($key) {
            app(MaiSessionService::class)->revokeKey($user, $key);
        }

        return redirect()->to('/mai')->with('message', 'Token revoked.');
    }

    protected function person()
    {
        if (! Auth::check()) {
            return null;
        }
        $user = Auth::user();
        if (! app(MaiCustomerAuth::class)->verified($user)) {
            return null;
        }

        return $user;
    }

    protected function owned($publicId)
    {
        $user = $this->person();
        if (! $user) {
            return null;
        }
        $session = MaiSession::where('public_id', $publicId)->first();
        if (! $session || ! $session->allows($user)) {
            return null;
        }

        return $session;
    }

    protected function managed($publicId)
    {
        $user = $this->person();
        if (! $user) {
            return null;
        }
        $session = MaiSession::where('public_id', $publicId)->first();
        if (! $session || ! $session->canManage($user)) {
            return null;
        }

        return $session;
    }
}
