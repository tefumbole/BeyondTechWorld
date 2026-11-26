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
            'linkTab' => session('link_tab') === 'code' ? 'code' : 'qr',
            'otpSent' => (bool) session('link_otp_sent'),
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
        $back = $request->input('next') === 'home' ? 'whatsapp.mai.home' : 'whatsapp.mai.link';
        if ($state['status'] !== 'CONNECTED') {
            return redirect()->route($back)->with('not_permitted', 'Approve WhatsApp on your phone before sending.');
        }
        $to = preg_replace('/\D/', '', (string) $request->input('to'));
        $text = trim((string) $request->input('text'));
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $to) || $text === '') {
            return redirect()->route($back)->with('not_permitted', 'Enter the other phone number and a message.');
        }
        $result = $engine->send($user->phone, $to, $text);
        if (empty($result['success'])) {
            return redirect()->route($back)->with('not_permitted', isset($result['error']) ? $result['error'] : 'The message was not sent.');
        }

        return redirect()->route($back)->with('message', 'Sent from your WhatsApp.');
    }

    protected function person()
    {
        if (! Auth::check() || ! Auth::user()) {
            return null;
        }

        return Auth::user();
    }
}
