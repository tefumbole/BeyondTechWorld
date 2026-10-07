<?php

namespace App\Http\Controllers;

use App\Services\BeyondWasenderService;
use App\Services\YongInvitationService;
use App\Support\CountryDialCodes;
use App\Support\WhatsAppPhone;
use App\YongGallery;
use App\YongInvitation;
use App\YongReview;
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
        if ($pledge !== null && $pledge > 0 && $pledge < 5000) {
            return $this->fail($request, 'Enter at least 5,000 FCFA, or leave the pledge blank.', 422);
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
        $name = trim($data['name']);
        if ($existing && $this->sameInvitation($existing, $name, $data['position'], $pledge)) {
            $row = $existing;
        } else {
            try {
                $row = $this->invitations->create($phone, $name, $data['position'], $pledge);
            } catch (\Throwable $e) {
                Log::warning('yong invitation compose failed: '.$e->getMessage());

                return $this->fail($request, 'Could not build the invitation. Please try again.', 500);
            }
            $this->invitations->forgetOthers($phone, $row->id);
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
        )->orderBy('created_at', 'desc')->first();
    }

    protected function sameInvitation(YongInvitation $row, $name, $position, $pledge)
    {
        $current = $row->pledge_amount === null ? null : (int) $row->pledge_amount;

        return strcasecmp(trim((string) $row->name), trim((string) $name)) === 0
            && (string) $row->position === (string) $position
            && $current === $pledge;
    }

    protected function fail(Request $request, $message, $status)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => false, 'message' => $message], $status);
        }

        return back()->withInput()->withErrors(['phone' => $message]);
    }

    public function ticket($code)
    {
        $row = YongInvitation::where('ticket_code', $code)->first();
        if (! $row) {
            abort(404);
        }

        return view('beyond.yong.ticket', ['invitation' => $row]);
    }

    public function eat($code)
    {
        $row = YongInvitation::where('ticket_code', $code)->first();
        if (! $row) {
            abort(404);
        }
        $this->invitations->markEaten($row);

        return redirect('/yong/ticket/'.$row->ticket_code);
    }

    public function attend($code)
    {
        $row = YongInvitation::where('ticket_code', $code)->first();
        if (! $row) {
            abort(404);
        }
        $this->invitations->markAttended($row);

        return redirect('/yong/ticket/'.$row->ticket_code.'?welcomed=1');
    }

    public function meals()
    {
        $rows = YongInvitation::query()->orderBy('ticket_code')->get();

        return view('beyond.yong.meals', [
            'invitations' => $rows,
            'eaten' => $rows->filter(function ($row) {
                return $row->eaten_at !== null;
            }),
        ]);
    }

    public function sendThanks()
    {
        $count = $this->invitations->queueReviewThanks();

        return redirect('/yong/meals?thanks='.$count);
    }

    public function storeReview(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);
        YongReview::create([
            'invitation_id' => $request->input('invitation_id'),
            'name' => trim($data['name']),
            'rating' => (int) $data['rating'],
            'comment' => isset($data['comment']) ? trim($data['comment']) : null,
        ]);

        return redirect('/yong?tab=reviews');
    }

    public function storeGallery(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:80',
            'caption' => 'nullable|string|max:160',
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);
        $dir = public_path('yong/gallery');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ext = strtolower($request->file('photo')->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $file = (string) \Illuminate\Support\Str::uuid().'.'.$ext;
        $request->file('photo')->move($dir, $file);
        YongGallery::create([
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'caption' => isset($data['caption']) ? trim($data['caption']) : null,
            'image_file' => $file,
        ]);

        return redirect('/yong?tab=gallery');
    }
}
