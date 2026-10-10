<?php

namespace App\Http\Controllers;

use App\PublicDonation;
use App\Services\BinancePayService;
use App\Services\CampayPayoutService;
use App\Services\ClientNoticeService;
use App\Services\StripeCheckoutService;
use App\Services\MobileMoneyHolderService;
use App\Support\CountryDialCodes;
use App\Support\TwilioAdminCopy;
use App\Support\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PublicDonateController extends Controller
{
    public function show()
    {
        return view('beyond.donate');
    }

    public function adminIndex()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403);
        }
        $pending = PublicDonation::whereIn('status', ['pending', 'waiting'])->orderByDesc('id')->limit(30)->get();
        foreach ($pending as $donation) {
            try {
                $this->refresh($donation);
            } catch (\Throwable $e) {
                \Log::warning('Donation status check failed', ['id' => $donation->id, 'error' => $e->getMessage()]);
            }
        }
        $rows = PublicDonation::orderByDesc('id')->limit(100)->get();

        return view('payment.donations', [
            'rows' => $rows,
            'tab' => 'donations',
            'paidTotal' => (int) PublicDonation::where('status', 'paid')->sum('amount'),
            'pendingTotal' => (int) PublicDonation::whereIn('status', ['pending', 'waiting'])->sum('amount'),
        ]);
    }

    public function lookup(Request $request)
    {
        $service = app(CampayPayoutService::class);
        $full = $this->fullPhone($request->get('country_code'), $request->get('phone'));
        $phone = $service->momoNumber($full);
        if (! $phone) {
            return response()->json([
                'ok' => true,
                'phone' => $full,
                'name' => '',
                'operator' => '',
            ]);
        }
        $hit = app(MobileMoneyHolderService::class)->lookup($phone);

        return response()->json([
            'ok' => true,
            'phone' => $phone,
            'name' => ($hit && ! empty($hit['name'])) ? $hit['name'] : '',
            'operator' => $service->operatorName($phone),
        ]);
    }

    public function store(Request $request)
    {
        $service = app(CampayPayoutService::class);
        $full = $this->fullPhone($request->input('country_code'), $request->input('phone'));
        $phone = $service->momoNumber($full);
        $name = trim((string) $request->input('person_name'));
        $amount = (int) $request->input('amount');
        $note = trim((string) $request->input('note'));
        $method = (string) $request->input('method');
        if (! in_array($method, ['momo', 'visa', 'crypto'], true)) {
            $method = 'momo';
        }
        if ($full === '') {
            return back()->withInput()->with('not_permitted', 'Enter a phone number.');
        }
        if ($method === 'momo' && ! $phone) {
            return back()->withInput()->with('not_permitted', 'MTN and Orange need a Cameroon number. Choose VISA or Crypto for another country.');
        }
        if (! $phone) {
            $phone = $full;
        }
        if ($name === '' || strlen($name) > 191) {
            return back()->withInput()->with('not_permitted', 'Enter the name on this number.');
        }
        if ($amount < 100 || $amount > 1000000) {
            return back()->withInput()->with('not_permitted', 'Enter an amount from 100 to 1,000,000 XAF.');
        }
        $donation = new PublicDonation();
        $donation->token = bin2hex(random_bytes(16));
        $donation->person_name = $name;
        $donation->phone = $phone;
        $donation->amount = $amount;
        $donation->note = $note !== '' ? substr($note, 0, 191) : null;
        $donation->method = $method;
        $donation->status = 'pending';
        $donation->save();
        $reference = 'don-'.date('YmdHis').'-'.$donation->id;
        $description = $note !== '' ? substr($note, 0, 80) : 'Donation';
        $return = route('donate.status', ['token' => $donation->token]);
        if ($method === 'crypto') {
            $result = app(BinancePayService::class)->instructions($amount, $donation->id);
            if (empty($result['ok'])) {
                $donation->status = 'failed';
                $donation->error = isset($result['error']) ? substr((string) $result['error'], 0, 250) : 'Binance could not open this payment.';
                $donation->save();

                return redirect($return)->with('not_permitted', $donation->error);
            }
            $donation->campay_reference = 'usdt:'.$result['usdt'];
            $donation->payment_link = json_encode([
                'address' => $result['address'],
                'network' => $result['network'],
                'usdt' => $result['usdt'],
                'coin' => $result['coin'],
            ]);
            $donation->save();

            return redirect($return);
        }
        if ($method === 'visa') {
            $card = app(StripeCheckoutService::class)->checkout(
                'Donation',
                $amount,
                $return.'?session_id={CHECKOUT_SESSION_ID}',
                $return,
                ['donation' => (string) $donation->id]
            );
            if (empty($card['ok'])) {
                $donation->status = 'failed';
                $donation->error = isset($card['error']) ? substr((string) $card['error'], 0, 250) : 'VISA could not be opened.';
                $donation->save();

                return redirect($return)->with('not_permitted', $donation->error);
            }
            $donation->campay_reference = (string) $card['id'];
            $donation->payment_link = (string) $card['url'];
            $donation->save();

            return redirect($return);
        }
        $body = $service->collect($phone, $amount, $reference, $description);
        $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        if (! is_array($body) || empty($body['reference']) || $status === 'FAILED') {
            $donation->status = 'failed';
            $donation->error = 'Campay could not ask for the Mobile Money approval.';
            $donation->save();

            return redirect($return)->with('not_permitted', $donation->error);
        }
        $donation->campay_reference = (string) $body['reference'];
        $donation->save();

        return redirect($return);
    }

    public function waiting($token)
    {
        $donation = $this->find($token);
        $this->refresh($donation);
        $donation = $donation->fresh();
        if ($donation->status === 'paid') {
            return redirect()->route('donate.show')->with('message', 'Your donation of '.number_format($donation->amount, 0, '.', ' ').' XAF has been received.');
        }

        return view('beyond.donate_status', ['donation' => $donation]);
    }

    public function status($token)
    {
        $donation = $this->find($token);
        $this->refresh($donation);
        $donation = $donation->fresh();

        return response()->json([
            'status' => $donation->status,
            'message' => $donation->status === 'paid' ? 'Donation received.' : ($donation->error ?: 'Waiting for approval.'),
        ]);
    }

    protected function refresh(PublicDonation $donation)
    {
        if ($donation->status === 'paid' || ! $donation->campay_reference) {
            return;
        }
        if ($donation->method === 'crypto') {
            $info = json_decode((string) $donation->payment_link, true);
            if (! is_array($info) || empty($info['address']) || empty($info['usdt'])) {
                return;
            }
            $since = $donation->created_at
                ? $donation->created_at->copy()->subMinutes(10)->getTimestamp() * 1000
                : (time() - 3600) * 1000;
            $tx = app(BinancePayService::class)->findPayment($info['address'], $info['usdt'], $since);
            if ($tx && ! PublicDonation::where('campay_reference', $tx)->exists()) {
                $donation->campay_reference = $tx;
                $donation->save();
                $this->markPaid($donation);
            }

            return;
        }
        if (strpos((string) $donation->campay_reference, 'cs_') === 0) {
            if (app(StripeCheckoutService::class)->isPaid($donation->campay_reference)) {
                $this->markPaid($donation);
            }

            return;
        }
        $body = app(CampayPayoutService::class)->transaction($donation->campay_reference);
        $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        if ($status === 'SUCCESSFUL') {
            $this->markPaid($donation);
        } elseif ($status === 'FAILED') {
            $donation->status = 'failed';
            $donation->error = 'The donation was not approved.';
            $donation->save();
        }
    }

    protected function markPaid(PublicDonation $donation)
    {
        if ($donation->status === 'paid') {
            return;
        }
        $updated = PublicDonation::where('id', $donation->id)->where('status', '!=', 'paid')->update([
            'status' => 'paid',
            'error' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        if (! $updated) {
            return;
        }
        $donation->status = 'paid';
        $donation->error = null;
        $amount = number_format($donation->amount, 0, '.', ' ');
        $note = trim((string) $donation->note);
        $method = $this->methodLabel($donation->method);
        $donor = WhatsAppMessage::statusBlock('✅', 'Donation Received');
        $donor .= 'Dear *'.$donation->person_name.'*,'."\n\n";
        $donor .= 'Your donation of *'.$amount."* XAF has been received.\n";
        $donor .= "\n".WhatsAppMessage::bullet('Paid with', $method);
        if ($note !== '') {
            $donor .= "\n".WhatsAppMessage::bullet('Note', $note);
        }
        $donor .= "\n".WhatsAppMessage::bullet('Date', date('d M Y'));
        $donor .= WhatsAppMessage::footer();
        app(ClientNoticeService::class)->send($donation->phone, $donor);
        $copy = WhatsAppMessage::statusBlock('✅', 'Donation Received');
        $copy .= 'Dear *'.$this->adminName().'*,'."\n\n";
        $copy .= 'A donation of *'.$amount.'* XAF has been received from *'.$donation->person_name."*.\n";
        $copy .= "\n".WhatsAppMessage::bullet('From', $donation->person_name);
        $copy .= "\n".WhatsAppMessage::bullet('Phone', $donation->phone);
        $copy .= "\n".WhatsAppMessage::bullet('Paid with', $method);
        $copy .= "\n".WhatsAppMessage::bullet('Amount', $amount.' XAF');
        if ($note !== '') {
            $copy .= "\n".WhatsAppMessage::bullet('Note', $note);
        }
        $copy .= "\n".WhatsAppMessage::bullet('Date', date('d M Y'));
        $copy .= WhatsAppMessage::footer();
        app(ClientNoticeService::class)->send(TwilioAdminCopy::PHONE, $copy);
    }

    protected function methodLabel($method)
    {
        if ($method === 'visa') {
            return 'VISA';
        }
        if ($method === 'crypto') {
            return 'Crypto';
        }

        return 'Momo/OM';
    }

    protected function adminName()
    {
        $tail = substr(preg_replace('/\D/', '', TwilioAdminCopy::PHONE), -9);
        $user = \App\User::query()->where('is_active', 1)
            ->where(function ($query) use ($tail) {
                $query->where('phone', 'like', '%'.$tail)->orWhere('additional_phone', 'like', '%'.$tail);
            })
            ->first(['name']);
        $name = $user ? trim((string) $user->name) : '';

        return $name !== '' ? $name : 'Admin';
    }

    protected function fullPhone($code, $number)
    {
        $codes = CountryDialCodes::all();
        $code = trim((string) $code);
        if (! isset($codes[$code])) {
            $code = '+237';
        }
        $full = CountryDialCodes::combine($code, $number);
        $digits = preg_replace('/\D/', '', $full);
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return '';
        }

        return $digits;
    }

    protected function find($token)
    {
        $donation = PublicDonation::where('token', $token)->first();
        if (! $donation) {
            abort(404);
        }

        return $donation;
    }
}
