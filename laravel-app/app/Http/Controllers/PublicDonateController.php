<?php

namespace App\Http\Controllers;

use App\PublicDonation;
use App\Services\CampayPayoutService;
use App\Services\ClientNoticeService;
use App\Services\Messaging\TwilioTemplateSender;
use App\Services\MobileMoneyHolderService;
use App\Support\TwilioAdminCopy;
use App\Support\WhatsAppMessage;
use Illuminate\Http\Request;

class PublicDonateController extends Controller
{
    public function show()
    {
        return view('beyond.donate');
    }

    public function lookup(Request $request)
    {
        $service = app(CampayPayoutService::class);
        $phone = $service->momoNumber($request->get('phone'));
        if (! $phone) {
            return response()->json(['ok' => false, 'error' => 'Not an MTN or Orange Cameroon number.']);
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
        $phone = $service->momoNumber($request->input('phone'));
        $name = trim((string) $request->input('person_name'));
        $amount = (int) $request->input('amount');
        $note = trim((string) $request->input('note'));
        $method = (string) $request->input('method');
        if (! $phone) {
            return back()->withInput()->with('not_permitted', 'Enter an MTN or Orange Cameroon number.');
        }
        if ($name === '' || strlen($name) > 191) {
            return back()->withInput()->with('not_permitted', 'Enter the name on this number.');
        }
        if ($amount < 100 || $amount > 1000000) {
            return back()->withInput()->with('not_permitted', 'Enter an amount from 100 to 1,000,000 XAF.');
        }
        if (! in_array($method, ['momo', 'visa'], true)) {
            $method = 'momo';
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
        if ($method === 'visa') {
            $body = $service->cardLink($phone, $amount, $reference, $description, $return, $name);
            if (! is_array($body) || empty($body['link'])) {
                $donation->status = 'failed';
                $donation->error = 'Campay did not return a VISA link.';
                $donation->save();

                return redirect($return)->with('not_permitted', $donation->error);
            }
            $donation->campay_reference = isset($body['reference']) ? (string) $body['reference'] : null;
            $donation->payment_link = (string) $body['link'];
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

        return view('beyond.donate_status', ['donation' => $donation->fresh()]);
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
        $donation->status = 'paid';
        $donation->error = null;
        $donation->save();
        $amount = number_format($donation->amount, 0, '.', ' ');
        $note = trim((string) $donation->note);
        $donor = WhatsAppMessage::statusBlock('✅', 'Donation Received');
        $donor .= 'Dear *'.$donation->person_name.'*,'."\n\n";
        $donor .= 'Your donation of *'.$amount."* XAF has been received.\n";
        if ($note !== '') {
            $donor .= "\n".WhatsAppMessage::bullet('Note', $note);
        }
        $donor .= WhatsAppMessage::footer();
        $detail = $donation->person_name.' donated '.$amount.' XAF.';
        if ($note !== '') {
            $detail .= ' Note: '.$note.'.';
        }
        $twilio = app(TwilioTemplateSender::class);
        $company = WhatsAppMessage::companyName();
        $donorResult = $twilio->sendSharedConfirmation(
            $donation->phone,
            $donation->person_name,
            $company,
            'donation',
            $note !== '' ? $note : 'Donation',
            date('d M Y'),
            'Your donation has been received.',
            $amount.' XAF'
        );
        if (empty($donorResult['success'])) {
            app(ClientNoticeService::class)->send($donation->phone, $donor);
        }
        $admin = TwilioAdminCopy::PHONE;
        $adminResult = $twilio->sendSharedStatus(
            $admin,
            $this->adminName(),
            $company,
            'donation',
            $donation->person_name,
            'Received',
            $detail
        );
        if (empty($adminResult['success'])) {
            $copy = WhatsAppMessage::statusBlock('✅', 'Donation Received');
            $copy .= 'Dear *'.$this->adminName().'*,'."\n\n";
            $copy .= $detail."\n";
            $copy .= WhatsAppMessage::footer();
            app(ClientNoticeService::class)->send($admin, $copy);
        }
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

    protected function find($token)
    {
        $donation = PublicDonation::where('token', $token)->first();
        if (! $donation) {
            abort(404);
        }

        return $donation;
    }
}
