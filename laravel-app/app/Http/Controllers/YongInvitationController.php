<?php

namespace App\Http\Controllers;

use App\Services\BeyondWasenderService;
use App\Services\YongInvitationService;
use App\Support\CountryDialCodes;
use App\Support\WhatsAppPhone;
use App\YongInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YongInvitationController extends Controller
{
    protected $invitations;
    protected $whatsapp;

    public function __construct(YongInvitationService $invitations, BeyondWasenderService $whatsapp)
    {
        $this->invitations = $invitations;
        $this->whatsapp = $whatsapp;
    }

    public function index()
    {
        return view('beyond.yong.index', [
            'countries' => CountryDialCodes::list(),
            'lookupUrl' => url('/yong/lookup'),
            'submitUrl' => url('/yong/submit'),
            'serviceAt' => $this->invitations->serviceAt()->toIso8601String(),
            'previews' => [
                'standard' => asset('public/yong/standard.jpg'),
                'gold' => asset('public/yong/gold.jpg'),
                'clergy' => asset('public/yong/clergy.jpg'),
            ],
        ]);
    }

    public function lookup(Request $request)
    {
        return app(PublicPhoneLookupController::class)->lookup($request);
    }

    public function submit(Request $request)
    {
        $data = $request->validate([
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'name' => 'required|string|max:80',
            'position' => 'required|in:friend,family,clergy,guest',
            'pledge_amount' => 'nullable|integer|min:0|max:50000000',
        ]);

        $pledge = $request->filled('pledge_amount') ? (int) $data['pledge_amount'] : null;
        if ($pledge !== null && $pledge > 0 && $pledge < 100) {
            return $this->fail($request, 'Enter at least 100 FCFA, or leave the pledge blank.', 422);
        }
        if ($pledge === 0) {
            $pledge = null;
        }

        try {
            $phone = WhatsAppPhone::combine($data['country_code'], $data['phone']);
        } catch (\Throwable $e) {
            return $this->fail($request, 'Enter a valid WhatsApp number.', 422);
        }
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return $this->fail($request, 'Enter a valid WhatsApp number.', 422);
        }

        $existing = $this->findByPhone($phone);
        if ($existing) {
            return $this->fail($request, 'This number already has an invitation. Open the one that was sent.', 409);
        }

        try {
            $row = $this->invitations->create($phone, trim($data['name']), $data['position'], $pledge);
        } catch (\Throwable $e) {
            Log::warning('yong invitation compose failed: '.$e->getMessage());

            return $this->fail($request, 'Could not build the invitation. Please try again.', 500);
        }

        $send = $this->invitations->sendToGuest($row);
        $waOk = ! empty($send['success']);
        if (! $waOk) {
            Log::info('yong WhatsApp send failed', ['error' => $send['error'] ?? 'unknown']);
        }
        $this->invitations->queuePastorCopy($row);

        $url = url('/yong/card/'.$row->id).($waOk ? '' : '?sent=0');
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'redirect' => $url, 'whatsapp' => $waOk]);
        }

        return redirect($url);
    }

    public function card($id)
    {
        $row = YongInvitation::find($id);
        if (! $row || ! is_file($row->imagePath())) {
            abort(404);
        }

        return view('beyond.yong.card', [
            'invitation' => $row,
            'sent' => request('sent') !== '0',
        ]);
    }

    public function pass($id)
    {
        $row = YongInvitation::find($id);
        if (! $row) {
            abort(404);
        }

        return view('beyond.yong.pass', [
            'invitation' => $row,
            'pay' => request('pay'),
        ]);
    }

    public function donate($id)
    {
        $row = YongInvitation::find($id);
        if (! $row || ! $row->isPremium()) {
            abort(404);
        }

        return view('beyond.yong.donate', [
            'invitation' => $row,
            'pay' => request('pay'),
        ]);
    }

    public function startPayment(Request $request, $id)
    {
        $row = YongInvitation::find($id);
        if (! $row || ! $row->isPremium()) {
            abort(404);
        }
        $method = $request->get('method') === 'visa' ? 'visa' : 'momo';
        try {
            $link = $this->invitations->paymentLink($row, $method);
        } catch (\Throwable $e) {
            return redirect()->to(url('/yong/donate/'.$row->id).'?pay=failed')
                ->with('pay_error', $e->getMessage());
        }

        return redirect()->away($link);
    }

    public function payment(Request $request)
    {
        $url = $this->invitations->handleCampay(
            $request->get('status'),
            $request->get('reference'),
            $request->get('external_reference')
        );

        return redirect()->to($url);
    }

    public function stripeReturn(Request $request)
    {
        return redirect()->to($this->invitations->handleStripe($request->get('session_id')));
    }

    protected function findByPhone($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        return YongInvitation::whereRaw(
            "REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?",
            [$digits]
        )->first();
    }

    protected function fail(Request $request, $message, $status)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        return back()->withInput()->withErrors(['phone' => $message]);
    }
}
