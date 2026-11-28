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
        $phone = $this->accountPhone($user);
        $session = ['status' => 'NOT_CONNECTED', 'qr' => null, 'pairing_code' => null, 'error' => null];
        if ($phone !== '') {
            $engine = app(MaiWhatsAppEngine::class);
            $session = $engine->status($phone, false);
            if ($this->linked($user, $phone, $session)) {
                return redirect()->route('whatsapp.mai.inbox');
            }
        }

        return view('cloud.portal.mai_link', [
            'phone' => $phone,
            'session' => $session,
        ]);
    }

    public function refreshQr(Request $request)
    {
        $user = $this->person();
        if (! $user) {
            return response()->json(['status' => 'NOT_CONNECTED'], 401);
        }
        $typed = preg_replace('/\D/', '', (string) app(\App\Services\Cloud\CloudMbolePrice::class)->e164($request->input('phone'), $request->input('dial')));
        $phone = preg_match('/^[1-9][0-9]{7,14}$/', $typed) ? $typed : $this->requestedPhone($user);
        if ($phone === '') {
            return response()->json(['status' => 'ERROR', 'error' => 'Enter the WhatsApp number with the country code.']);
        }
        if (\App\Services\Mai\MaiBusinessLine::is($phone)) {
            return response()->json(['status' => 'ERROR', 'error' => 'Use your personal WhatsApp number. The business line stays separate.']);
        }
        if (! \App\Services\Mai\MaiPilot::allows($phone)) {
            return response()->json(['status' => 'ERROR', 'error' => 'This WhatsApp is not in the current MAI pilot.']);
        }
        $own = $this->accountPhone($user);
        if ($own !== '' && substr($own, -8) !== substr($phone, -8)) {
            return response()->json(['status' => 'ERROR', 'error' => 'This account already uses a different WhatsApp number.']);
        }
        session(['mai_link_phone' => $phone]);
        $state = app(MaiWhatsAppEngine::class)->refreshQr($phone);

        return response()->json($this->linkJson($user, $phone, $state));
    }

    public function sendLinkOtp()
    {
        $user = $this->person();
        if (! $user) {
            return redirect()->to(url('/login?mai=1'));
        }
        return redirect()->route('whatsapp.mai.link');
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

    public function status(Request $request)
    {
        $user = $this->person();
        if (! $user) {
            return response()->json(['status' => 'NOT_CONNECTED'], 401);
        }
        $phone = $this->requestedPhone($user);
        if ($phone === '') {
            return response()->json(['status' => 'NOT_CONNECTED', 'qr' => null, 'pairing_code' => null]);
        }
        $engine = app(MaiWhatsAppEngine::class);
        $state = $engine->status($phone, $request->boolean('qr'));

        return response()->json($this->linkJson($user, $phone, $state));
    }

    public function inbox(Request $request)
    {
        return redirect()->route('whatsapp.chats');
    }

    public function pair(Request $request)
    {
        $user = $this->person();
        if (! $user) {
            return response()->json(['status' => 'NOT_CONNECTED'], 401);
        }
        $phone = preg_replace('/\D/', '', (string) app(\App\Services\Cloud\CloudMbolePrice::class)->e164($request->input('phone'), $request->input('dial')));
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $phone)) {
            return response()->json(['status' => 'ERROR', 'error' => 'Enter the WhatsApp number with the country code.']);
        }
        if (\App\Services\Mai\MaiBusinessLine::is($phone)) {
            return response()->json(['status' => 'ERROR', 'error' => 'Use your personal WhatsApp number. The business line stays separate.']);
        }
        if (! \App\Services\Mai\MaiPilot::allows($phone)) {
            return response()->json(['status' => 'ERROR', 'error' => 'This WhatsApp is not in the current MAI pilot.']);
        }
        $own = $this->accountPhone($user);
        if ($own !== '' && substr($own, -8) !== substr($phone, -8)) {
            return response()->json(['status' => 'ERROR', 'error' => 'This account already uses a different WhatsApp number.']);
        }
        $owned = \App\Mai\MaiSession::where('worker_session_id', 'p'.$phone)->first();
        if ($owned && (int) $owned->user_id !== (int) $user->id) {
            return response()->json(['status' => 'ERROR', 'error' => 'That WhatsApp is already linked to another MAI account.']);
        }
        session(['mai_link_phone' => $phone]);
        $state = app(MaiWhatsAppEngine::class)->pair($phone, $request->boolean('fresh') || $request->input('fresh') === '1');

        return response()->json($this->linkJson($user, $phone, $state, true));
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

    protected function accountPhone($user)
    {
        $digits = preg_replace('/\D/', '', (string) $user->phone);

        return preg_match('/^[1-9][0-9]{7,14}$/', $digits) ? $digits : '';
    }

    protected function requestedPhone($user)
    {
        $asked = preg_replace('/\D/', '', (string) session('mai_link_phone'));
        if (preg_match('/^[1-9][0-9]{7,14}$/', $asked)) {
            return $asked;
        }

        return $this->accountPhone($user);
    }

    protected function linked($user, $expected, array $state)
    {
        if (($state['status'] ?? '') !== 'CONNECTED') {
            return false;
        }
        $phone = preg_replace('/\D/', '', (string) (isset($state['phone']) ? $state['phone'] : ''));
        if ($phone === '' || substr($phone, -8) !== substr($expected, -8)) {
            return false;
        }
        if (\App\Services\Mai\MaiBusinessLine::is($phone)) {
            return false;
        }
        if ($this->accountPhone($user) === '') {
            $user->phone = $phone;
            $user->save();
        }
        app(\App\Services\Mai\PersonalHub::class)->ensure($user->fresh() ?: $user);
        app(\App\Services\Cloud\CloudSignupOtp::class)->sendAppLinkOnce($phone);

        return true;
    }

    protected function linkJson($user, $expected, array $state, $hideQr = false)
    {
        $phone = preg_replace('/\D/', '', (string) (isset($state['phone']) ? $state['phone'] : ''));
        if (($state['status'] ?? '') === 'CONNECTED' && $phone !== '' && substr($phone, -8) !== substr($expected, -8)) {
            return [
                'status' => 'ERROR',
                'qr' => null,
                'pairing_code' => null,
                'error' => 'This WhatsApp is a different number.',
            ];
        }
        if (($state['status'] ?? '') === 'CONNECTED' && \App\Services\Mai\MaiBusinessLine::is($phone)) {
            return [
                'status' => 'ERROR',
                'qr' => null,
                'pairing_code' => null,
                'error' => 'Use your personal WhatsApp number. The business line stays separate.',
            ];
        }
        $redirect = $this->linked($user, $expected, $state) ? route('whatsapp.mai.inbox') : null;

        return [
            'status' => $redirect ? 'CONNECTED' : (isset($state['status']) ? $state['status'] : 'NOT_CONNECTED'),
            'qr' => $hideQr ? null : (isset($state['qr']) ? $state['qr'] : null),
            'qr_age_ms' => isset($state['qr_age_ms']) ? (int) $state['qr_age_ms'] : 0,
            'pairing_code' => isset($state['pairing_code']) ? $state['pairing_code'] : null,
            'pairing_age_ms' => isset($state['pairing_age_ms']) ? (int) $state['pairing_age_ms'] : 0,
            'error' => isset($state['error']) ? $state['error'] : null,
            'redirect' => $redirect,
        ];
    }
}
