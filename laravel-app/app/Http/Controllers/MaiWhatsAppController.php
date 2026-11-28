<?php

namespace App\Http\Controllers;

use App\Services\Cloud\MaiWhatsAppEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in person's own WhatsApp, inside Whats MAI.
 */
class MaiWhatsAppController extends Controller
{
    public function show()
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to(url('/login?mai=1'));
        }
        $engine = app(MaiWhatsAppEngine::class);
        $session = $engine->open($user->phone);
        if (($session['status'] ?? '') === 'CONNECTED') {
            app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLinkOnce($user->phone);

            return redirect()->route('whatsapp.mai.inbox');
        }
        if (empty($session['qr'])) {
            $session = $engine->refreshQr($user->phone);
        }

        return view('cloud.portal.mai_link', [
            'phone' => $user->phone,
            'session' => $session,
        ]);
    }

    public function refreshQr()
    {
        $user = $this->person();
        if (! $user) {
            return response()->json(['status' => 'NOT_CONNECTED'], 401);
        }
        $state = app(MaiWhatsAppEngine::class)->refreshQr($user->phone);
        $redirect = null;
        if (($state['status'] ?? '') === 'CONNECTED' && ! \App\Services\Mai\MaiBusinessLine::is($user->phone)) {
            $redirect = route('whatsapp.mai.inbox');
        }

        return response()->json([
            'status' => $state['status'],
            'qr' => $state['qr'],
            'qr_age_ms' => isset($state['qr_age_ms']) ? (int) $state['qr_age_ms'] : 0,
            'error' => $state['error'],
            'redirect' => $redirect,
        ]);
    }

    public function sendLinkOtp()
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to(url('/login?mai=1'));
        }
        $state = app(MaiWhatsAppEngine::class)->pair($user->phone);
        $code = isset($state['pairing_code']) ? (string) $state['pairing_code'] : '';
        if ($code === '') {
            $error = ! empty($state['error']) ? $state['error'] : 'The link code could not be created.';

            return redirect()->route('whatsapp.mai.link')->with('not_permitted', $error)->with('link_tab', 'code');
        }
        $sent = false;
        $body = "Whats MAI link code: ".$code."\nOn your phone, open WhatsApp, tap Linked devices, then Link with phone number, and enter this code.";
        try {
            $result = app(\App\Services\Messaging\NotificationRouter::class)->sendWhatsAppTextWithLink($user->phone, $body);
            $sent = is_array($result) && ! empty($result['success']) && empty($result['skipped']);
        } catch (\Exception $e) {
            $sent = false;
        }
        $notice = $sent
            ? 'The link code was sent to this phone. Enter it in WhatsApp under Linked devices.'
            : 'The link code is on this screen. It could not be delivered to the phone.';

        return redirect()->route('whatsapp.mai.link')
            ->with('message', $notice)
            ->with('link_tab', 'code')
            ->with('link_code', $code);
    }

    public function verifyLinkOtp(Request $request)
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to(url('/login?mai=1'));
        }
        try {
            app(\App\Services\Cloud\CloudSignupOtp::class)->verify($user->phone, $request->input('code'));
        } catch (\Exception $e) {
            return redirect()->route('whatsapp.mai.link')
                ->with('not_permitted', $e->getMessage())
                ->with('link_tab', 'code')
                ->with('link_otp_sent', 1);
        }
        app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLink($user->phone);
        $computer = $user
            ? \URL::temporarySignedRoute('whatsapp.mai.enter', now()->addDays(14), ['user' => $user->id])
            : route('whatsapp.mai.home');

        return redirect()->route('whatsapp.mai.home')->with('message', 'Whats MAI is open for this phone. Open it on this computer too: '.$computer);
    }

    public function status()
    {
        $user = $this->person();
        if (! $user) {
            return response()->json(['status' => 'NOT_CONNECTED'], 401);
        }
        $engine = app(MaiWhatsAppEngine::class);
        $state = $engine->status($user->phone, true);

        $redirect = null;
        if ($state['status'] === 'CONNECTED' && ! \App\Services\Mai\MaiBusinessLine::is($user->phone)) {
            app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLinkOnce($user->phone);
            $redirect = route('whatsapp.mai.inbox');
        }

        return response()->json([
            'status' => $state['status'],
            'qr' => $state['qr'],
            'qr_age_ms' => isset($state['qr_age_ms']) ? (int) $state['qr_age_ms'] : 0,
            'error' => $state['error'],
            'redirect' => $redirect,
            'messages' => $state['status'] === 'CONNECTED' ? $engine->messages($user->phone) : [],
        ]);
    }

    public function inbox(Request $request)
    {
        return redirect()->route('whatsapp.chats');
    }

    public function pair()
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to(url('/login?mai=1'));
        }
        app(MaiWhatsAppEngine::class)->pair($user->phone);

        return redirect()->route('whatsapp.mai.link');
    }

    public function send(Request $request)
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to(url('/login?mai=1'));
        }
        $engine = app(MaiWhatsAppEngine::class);
        $state = $engine->status($user->phone, false);
        $home = $request->input('next') === 'home';
        $backTo = function ($extra = []) use ($home, $request) {
            if (! $home) {
                return redirect()->route('whatsapp.mai.link');
            }
            $chat = preg_replace('/\D/', '', (string) $request->input('to'));

            return redirect()->route('whatsapp.mai.home', $chat !== '' ? ['chat' => $chat] : []);
        };
        if ($state['status'] !== 'CONNECTED') {
            return $backTo()->with('not_permitted', 'Approve WhatsApp on your phone before sending.');
        }
        $to = trim((string) $request->input('to'));
        if (substr($to, -5) !== '@g.us') {
            $to = preg_replace('/\D/', '', $to);
        }
        $text = trim((string) $request->input('text'));
        if ($to === '' || $text === '') {
            return $backTo()->with('not_permitted', 'Enter the other phone number and a message.');
        }
        if ($request->input('next') === 'inbox') {
            $sent = $engine->send($user->phone, $to, $text);
            $back = redirect()->route('whatsapp.mai.inbox', ['jid' => $request->input('to')]);
            if (empty($sent['success'])) {
                return $back->with('not_permitted', isset($sent['error']) ? $sent['error'] : 'The message was not sent.');
            }

            return $back;
        }
        $result = $engine->send($user->phone, $to, $text);
        if (empty($result['success'])) {
            return $backTo()->with('not_permitted', isset($result['error']) ? $result['error'] : 'The message was not sent.');
        }

        return $backTo()->with('message', 'Sent from your WhatsApp.');
    }

    protected function person()
    {
        if (! Auth::check() || ! Auth::user()) {
            return null;
        }

        return Auth::user();
    }
}
