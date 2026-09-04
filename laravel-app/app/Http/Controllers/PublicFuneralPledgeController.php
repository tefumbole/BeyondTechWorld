<?php

namespace App\Http\Controllers;

use App\Services\FuneralPledgeService;
use App\Services\PeopleDirectoryService;
use App\Support\CountryDialCodes;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;

class PublicFuneralPledgeController extends Controller
{
    protected $pledges;

    public function __construct(FuneralPledgeService $pledges)
    {
        $this->pledges = $pledges;
    }

    public function index(Request $request)
    {
        $this->guardEnabled();
        $data = $this->pledges->pageData();
        if (! $data) {
            abort(404);
        }

        $photos = [
            asset('public/memorial/pangwayu/studio.jpg'),
            asset('public/memorial/pangwayu/cbc.jpg'),
            asset('public/memorial/pangwayu/military.jpg'),
        ];

        return view('beyond.memorial.pangwayu', [
            'campaign' => $data['campaign'],
            'items' => $data['items'],
            'groups' => $data['groups'],
            'raised' => $data['raised'],
            'target' => $data['target'],
            'percent' => $data['percent'],
            'funeralAt' => $data['funeral_at'],
            'photos' => $photos,
            'countries' => CountryDialCodes::list(),
            'lookupUrl' => route('funeral.pangwayu.lookup'),
            'eulogies' => $data['eulogies'],
            'pledgeUrl' => route('funeral.pangwayu.store'),
            'eulogyUrl' => route('funeral.pangwayu.eulogy'),
            'flashPay' => $request->get('pay'),
            'flashEulogy' => $request->get('eulogy'),
        ]);
    }

    public function remember(Request $request)
    {
        $this->guardEnabled();
        $data = $this->pledges->pageData();
        if (! $data) {
            abort(404);
        }
        $gift = $this->pledges->openGiftItem();

        return view('beyond.memorial.remember', [
            'navActive' => 'remember',
            'campaign' => $data['campaign'],
            'funeralAt' => $data['funeral_at'],
            'photos' => [$this->pledges->rememberPhotos()[0]],
            'eulogies' => $data['eulogies'],
            'countries' => CountryDialCodes::list(),
            'lookupUrl' => route('funeral.pangwayu.lookup'),
            'eulogyUrl' => route('funeral.pangwayu.eulogy'),
            'pledgeUrl' => route('funeral.pangwayu.store'),
            'giftItemId' => $gift ? $gift->id : null,
            'flashEulogy' => $request->get('eulogy'),
            'flashPay' => $request->get('pay'),
        ]);
    }

    public function program()
    {
        $this->guardEnabled();

        $data = $this->pledges->pageData();
        if (! $data) {
            abort(404);
        }

        return view('beyond.memorial.program', [
            'navActive' => 'program',
            'photos' => $this->pledges->rememberPhotos(),
            'funeralAt' => $data['funeral_at'],
            'campaign' => $data['campaign'],
        ]);
    }

    public function hymns()
    {
        $this->guardEnabled();
        $data = $this->pledges->pageData();
        if (! $data) {
            abort(404);
        }

        return view('beyond.memorial.hymns', [
            'navActive' => 'hymns',
            'photos' => $this->pledges->rememberPhotos(),
            'funeralAt' => $data['funeral_at'],
            'campaign' => $data['campaign'],
        ]);
    }

    public function lookup(Request $request)
    {
        $this->guardEnabled();
        $phone = $this->resolvePhone($request);
        if ($phone === '') {
            return response()->json(['ok' => false, 'found' => false]);
        }

        return response()->json(app(PeopleDirectoryService::class)->lookupPhoneForForm($phone));
    }

    public function store(Request $request)
    {
        $this->guardEnabled();
        $data = $request->validate([
            'item_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'amount' => 'required|integer|min:100',
            'action' => 'required|in:pledge,pay',
            'pay_method' => 'nullable|in:momo,visa',
        ]);

        try {
            $phone = WhatsAppPhone::combine($data['country_code'], $data['phone']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid phone number.'], 422);
        }
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid phone number.'], 422);
        }

        try {
            $pledge = $this->pledges->createPledge([
                'item_id' => $data['item_id'],
                'name' => trim($data['name']),
                'phone' => $phone,
                'amount' => (int) $data['amount'],
                'action' => $data['action'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not save this pledge. Try again.'], 500);
        }

        if ($data['action'] === 'pay') {
            try {
                $link = $this->pledges->paymentLink(
                    $pledge,
                    $data['pay_method'] ?? 'momo',
                    $request->get('back') === 'remember' ? 'remember' : null
                );

                return response()->json(['ok' => true, 'redirect' => $link]);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }
        }

        return response()->json(['ok' => true, 'reload' => true]);
    }

    public function storeEulogy(Request $request)
    {
        $this->guardEnabled();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'body' => 'required|string|max:4000',
            'signature' => $request->get('from') === 'remember' ? 'required|string|max:900000' : 'nullable|string|max:900000',
            'selfie' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'from' => 'nullable|in:remember',
        ]);

        try {
            $phone = WhatsAppPhone::combine($data['country_code'], $data['phone']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid phone number.'], 422);
        }
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid phone number.'], 422);
        }

        try {
            $this->pledges->createEulogy([
                'name' => trim($data['name']),
                'phone' => $phone,
                'body' => $data['body'],
                'signature' => $data['signature'] ?? '',
                'selfie' => $request->file('selfie'),
                'require_signature' => ($data['from'] ?? '') === 'remember',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not save the eulogy. Try again.'], 500);
        }

        return response()->json(['ok' => true, 'reload' => true]);
    }

    public function stripeReturn(Request $request)
    {
        $this->guardEnabled();
        $result = $this->pledges->handleStripeReturn($request->get('session_id'), $request->get('back'));

        return redirect()->to($result['redirect']);
    }

    public function payment(Request $request)
    {
        $this->guardEnabled();
        $result = $this->pledges->handlePaymentCallback(
            $request->get('status'),
            $request->get('reference'),
            $request->get('external_reference')
        );

        return redirect()->to($result['redirect']);
    }

    protected function resolvePhone(Request $request)
    {
        $raw = trim((string) $request->get('phone', ''));
        $code = trim((string) $request->get('country_code', ''));
        if ($code !== '' && $raw !== '') {
            try {
                return WhatsAppPhone::combine($code, $raw);
            } catch (\InvalidArgumentException $e) {
                return '';
            }
        }
        if ($raw === '') {
            return '';
        }
        try {
            return WhatsAppPhone::sanitizeForStorage($raw);
        } catch (\Throwable $e) {
            return preg_replace('/\D/', '', $raw);
        }
    }

    protected function guardEnabled()
    {
        if (! $this->pledges->isEnabled()) {
            abort(404);
        }
    }
}
