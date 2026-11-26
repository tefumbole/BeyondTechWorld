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
        $engine->open($user->phone);

        return view('cloud.portal.mai_link', [
            'phone' => $user->phone,
            'session' => $engine->status($user->phone, true),
            'messages' => $engine->messages($user->phone),
        ]);
    }

    public function status()
    {
        $user = $this->person();
        if (! $user) {
            return response()->json(['status' => 'NOT_CONNECTED'], 401);
        }
        $engine = app(MaiWhatsAppEngine::class);
        $state = $engine->status($user->phone, true);

        return response()->json([
            'status' => $state['status'],
            'qr' => $state['qr'],
            'pairing_code' => $state['pairing_code'],
            'error' => $state['error'],
            'messages' => $state['status'] === 'CONNECTED' ? $engine->messages($user->phone) : [],
        ]);
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
        if ($state['status'] !== 'CONNECTED') {
            return redirect()->route('whatsapp.mai.link')->with('not_permitted', 'Approve WhatsApp on your phone before sending.');
        }
        $to = preg_replace('/\D/', '', (string) $request->input('to'));
        $text = trim((string) $request->input('text'));
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $to) || $text === '') {
            return redirect()->route('whatsapp.mai.link')->with('not_permitted', 'Enter the other phone number and a message.');
        }
        $result = $engine->send($user->phone, $to, $text);
        if (empty($result['success'])) {
            return redirect()->route('whatsapp.mai.link')->with('not_permitted', isset($result['error']) ? $result['error'] : 'The message was not sent.');
        }

        return redirect()->route('whatsapp.mai.link')->with('message', 'Sent from your WhatsApp.');
    }

    protected function person()
    {
        if (! Auth::check() || ! Auth::user()) {
            return null;
        }

        return Auth::user();
    }
}
