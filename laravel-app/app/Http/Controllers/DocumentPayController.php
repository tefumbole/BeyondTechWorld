<?php

namespace App\Http\Controllers;

use App\Account;
use App\DocumentPaymentLink;
use App\Payment;
use App\Quotation;
use App\Sale;
use App\Services\CampayPayoutService;
use App\Services\ClientNoticeService;
use App\Services\StripeCheckoutService;
use App\Services\Cloud\CloudTenantContext;
use App\Support\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class DocumentPayController extends Controller
{
    public function requestQuotation($id)
    {
        $this->guard();
        $document = Quotation::with('customer')->findOrFail($id);
        $amount = (int) round((float) $document->grand_total);
        if (abs((float) $document->grand_total - $amount) > 0.001 || $amount < 100) {
            return back()->with('not_permitted', 'The quotation total must be a whole amount of at least 100 XAF.');
        }

        return $this->sendRequest('quotation', $document, $amount, 'Quotation');
    }

    public function requestSale(Request $request, $id)
    {
        $this->guard();
        $document = Sale::with('customer')->findOrFail($id);
        $due = (float) $document->grand_total - (float) $document->paid_amount;
        $whole = (int) round($due);
        if (abs($due - $whole) > 0.001 || $whole < 100) {
            return back()->with('message', 'There is no whole amount of at least 100 XAF left to request.');
        }
        $asked = $request->filled('amount')
            ? (int) round((float) str_replace([',', ' '], '', (string) $request->input('amount')))
            : $whole;
        if ($asked < 100 || $asked > $whole) {
            return back()->with('not_permitted', 'Enter a whole amount from 100 XAF up to the '.$whole.' XAF still due.');
        }

        return $this->sendRequest('sale', $document, $asked, 'Invoice');
    }

    public function requestMany(array $ids)
    {
        $this->guard();
        $sent = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            $document = Sale::with('customer')->find((int) $id);
            if (! $document || (int) $document->payment_status === 4) {
                $skipped++;
                continue;
            }
            $due = (float) $document->grand_total - (float) $document->paid_amount;
            $amount = (int) round($due);
            if (abs($due - $amount) > 0.001 || $amount < 100) {
                $skipped++;
                continue;
            }
            if ($this->queueRequest('sale', $document, $amount, 'Invoice') !== null) {
                $skipped++;
                continue;
            }
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    public function show($token)
    {
        $link = $this->find($token);
        $this->refresh($link);
        $link = $link->fresh();
        $cached = Cache::get('docpay:'.$link->id);
        $pendingLines = is_array($cached) && ! empty($cached['lines']) ? $cached['lines'] : [];

        return view('pay.document', ['link' => $link, 'pendingLines' => $pendingLines]);
    }

    public function pay(Request $request, $token)
    {
        $link = $this->find($token);
        $this->refresh($link);
        $link = $link->fresh();
        if ($link->status === 'paid') {
            return redirect()->route('document.pay', ['token' => $link->token]);
        }
        if ($link->status === 'pending') {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'A payment is already waiting for approval.');
        }
        $method = (string) ($request->input('pay_with') ?: $request->input('method'));
        if (! in_array($method, ['momo', 'visa'], true)) {
            return redirect()->route('document.pay', ['token' => $link->token]);
        }
        $typedPhone = trim((string) $request->input('phone'));
        if ($typedPhone !== '') {
            $link->phone = $typedPhone;
        }
        $due = (int) $link->amount;
        if ($request->input('pay_mode') === 'split') {
            return $this->startSplit($request, $link, $method, $due);
        }
        $charge = (int) round((float) str_replace([',', ' '], '', (string) $request->input('amount', $due)));
        if ($charge < 100 || $charge > $due) {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'Enter a whole amount from 100 XAF up to the amount still due.');
        }
        $service = app(CampayPayoutService::class);
        $phone = $service->momoNumber($link->phone);
        if ($method === 'momo' && ! $phone) {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'This number is not an MTN or Orange Cameroon number. Pay with VISA.');
        }
        $from = $phone ?: preg_replace('/\D/', '', (string) $link->phone);
        if ($from === '') {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'Enter the number that should pay.');
        }
        Cache::put('docpay:'.$link->id, ['charge' => $charge, 'phone' => $from], now()->addDays(7));
        $description = substr($this->label($link).' '.$link->reference_no, 0, 80);
        if ($method === 'momo') {
            $asked = $this->askMomo($link, $from, $charge, $description);
            if (! empty($asked['error'])) {
                $link->status = 'failed';
                $link->error = $asked['error'];
                $link->save();

                return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', $asked['error']);
            }
            $link->method = 'momo';
            $link->phone = $from;
            $link->campay_reference = $asked['reference'];
            $link->status = 'pending';
            $link->error = null;
            $link->save();

            return redirect()->route('document.pay', ['token' => $link->token]);
        }
        $back = route('document.pay', ['token' => $link->token]);
        $card = app(StripeCheckoutService::class)->checkout(
            $description,
            $charge,
            $back.'?session_id={CHECKOUT_SESSION_ID}',
            $back,
            ['document' => (string) $link->id]
        );
        if (empty($card['ok'])) {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', isset($card['error']) ? $card['error'] : 'VISA could not be opened.');
        }
        $link->method = 'visa';
        $link->phone = $from;
        $link->campay_reference = (string) $card['id'];
        $link->payment_link = (string) $card['url'];
        $link->status = 'pending';
        $link->error = null;
        $link->save();

        return redirect()->route('document.pay', ['token' => $link->token]);
    }

    public function status($token)
    {
        $link = $this->find($token);
        $this->refresh($link);
        $link = $link->fresh();

        return response()->json([
            'status' => $link->status,
            'message' => $link->status === 'paid' ? 'Payment received.' : ($link->error ?: 'Waiting for approval.'),
        ]);
    }

    protected function sendRequest($kind, $document, $amount, $label)
    {
        $error = $this->queueRequest($kind, $document, $amount, $label);
        if ($error) {
            return back()->with('not_permitted', $error);
        }

        return back()->with('message', 'Payment link sent to '.$document->customer->name.'.');
    }

    protected function queueRequest($kind, $document, $amount, $label)
    {
        $customer = $document->customer;
        if (! $customer || trim((string) $customer->phone_number) === '') {
            return 'This client does not have a phone number.';
        }
        $link = DocumentPaymentLink::where('kind', $kind)->where('document_id', $document->id)->where('status', '!=', 'paid')->orderByDesc('id')->first();
        if (! $link) {
            $link = new DocumentPaymentLink();
            $link->token = bin2hex(random_bytes(16));
            $link->kind = $kind;
            $link->document_id = $document->id;
            $link->cloud_tenant_id = $document->cloud_tenant_id;
        }
        $link->reference_no = (string) $document->reference_no;
        $link->person_name = (string) $customer->name;
        $link->phone = (string) $customer->phone_number;
        $link->requested_by = Auth::user() ? (string) Auth::user()->name : '';
        $link->amount = $amount;
        $link->status = $link->status === 'pending' ? 'pending' : 'waiting';
        $link->save();
        $this->tell($link, route('document.pay', ['token' => $link->token]));

        return null;
    }

    protected function tell(DocumentPaymentLink $link, $url)
    {
        $label = $this->label($link);
        $msg = WhatsAppMessage::statusBlock('💳', 'Payment Request');
        $msg .= 'Dear *'.$link->person_name.'*,'."\n\n";
        $msg .= 'You have been requested to pay *'.number_format($link->amount, 0, '.', ' ')."* XAF for ".$label.' '.$link->reference_no.".\n";
        if (trim((string) $link->requested_by) !== '') {
            $msg .= "\n".WhatsAppMessage::bullet('Requested by', $link->requested_by);
        }
        $msg .= WhatsAppMessage::actionLink('Pay', $url);
        $msg .= WhatsAppMessage::footer();
        app(ClientNoticeService::class)->send($link->phone, $msg);
    }

    protected function startSplit(Request $request, DocumentPaymentLink $link, $method, $due)
    {
        if ($method !== 'momo') {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'Split payment is sent to Mobile Money numbers. VISA pays one amount.');
        }
        $phones = (array) $request->input('split_phone', []);
        $amounts = (array) $request->input('split_amount', []);
        $lines = [];
        foreach ($amounts as $i => $raw) {
            $amount = (int) round((float) str_replace([',', ' '], '', (string) $raw));
            $phone = isset($phones[$i]) ? trim((string) $phones[$i]) : '';
            if ($amount <= 0 && $phone === '') {
                continue;
            }
            $lines[] = ['phone' => $phone, 'amount' => $amount];
        }
        if (count($lines) < 2) {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'Add at least two numbers and amounts.');
        }
        $sum = 0;
        foreach ($lines as $line) {
            $sum += $line['amount'];
            if ($line['amount'] < 100) {
                return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'Each share must be at least 100 XAF.');
            }
        }
        if ($sum > $due) {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'The split is more than the amount still due.');
        }
        $description = substr($this->label($link).' '.$link->reference_no, 0, 80);
        $pending = [];
        foreach ($lines as $line) {
            $asked = $this->askMomo($link, $line['phone'], $line['amount'], $description);
            if (! empty($asked['error'])) {
                if (! $pending) {
                    return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', $asked['error']);
                }
                continue;
            }
            $pending[] = $asked;
        }
        if (! $pending) {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'None of the numbers could be asked to pay.');
        }
        Cache::put('docpay:'.$link->id, ['lines' => $pending], now()->addDays(7));
        $link->method = 'momo';
        $link->campay_reference = $pending[0]['reference'];
        $link->status = 'pending';
        $link->error = null;
        $link->save();

        return redirect()->route('document.pay', ['token' => $link->token]);
    }

    protected function askMomo(DocumentPaymentLink $link, $phone, $amount, $description)
    {
        $service = app(CampayPayoutService::class);
        $from = $service->momoNumber($phone);
        if (! $from) {
            return ['error' => 'Enter an MTN or Orange Cameroon number.'];
        }
        $external = 'doc-'.date('YmdHis').'-'.$link->id.'-'.substr(md5(uniqid('', true)), 0, 6);
        $body = $service->collect($from, (int) $amount, $external, $description);
        $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        if (! is_array($body) || empty($body['reference']) || $status === 'FAILED') {
            return ['error' => 'Campay could not ask for the Mobile Money approval.'];
        }

        return [
            'phone' => $from,
            'amount' => (int) $amount,
            'reference' => (string) $body['reference'],
            'external' => $external,
        ];
    }

    protected function refresh(DocumentPaymentLink $link)
    {
        if ($link->status === 'paid') {
            return;
        }
        $cached = Cache::get('docpay:'.$link->id);
        if (is_array($cached) && ! empty($cached['lines']) && count($cached['lines']) > 1) {
            $this->settleLines($link, $cached);

            return;
        }
        if (! $link->campay_reference) {
            return;
        }
        if (strpos((string) $link->campay_reference, 'cs_') === 0) {
            if (app(StripeCheckoutService::class)->isPaid($link->campay_reference)) {
                $charge = is_array($cached) && ! empty($cached['charge']) ? (int) $cached['charge'] : (int) $link->amount;
                $this->applyPortion($link, $charge, (string) $link->campay_reference, $link->phone, 'visa');
                Cache::forget('docpay:'.$link->id);
            }

            return;
        }
        $charge = is_array($cached) && ! empty($cached['charge']) ? (int) $cached['charge'] : (int) $link->amount;
        $phone = is_array($cached) && ! empty($cached['phone']) ? $cached['phone'] : $link->phone;
        $outcome = app(CampayPayoutService::class)->collectionOutcome($link->campay_reference, $charge, $phone);
        if ($outcome['state'] === 'paid' && ! empty($outcome['confirmed'])) {
            $bill = ! empty($outcome['bill']) ? (int) $outcome['bill'] : $charge;
            $this->applyPortion(
                $link,
                $bill,
                (string) $link->campay_reference,
                $phone,
                'momo',
                isset($outcome['fee']) ? (int) $outcome['fee'] : 0,
                isset($outcome['operator']) ? (string) $outcome['operator'] : ''
            );
            Cache::forget('docpay:'.$link->id);
        } elseif ($outcome['state'] === 'failed' && ! empty($outcome['confirmed'])) {
            $link->status = 'failed';
            $link->error = 'The payment was not approved.';
            $link->campay_reference = null;
            $link->save();
            Cache::forget('docpay:'.$link->id);
        }
    }

    protected function settleLines(DocumentPaymentLink $link, array $cached)
    {
        $lines = $cached['lines'];
        $waiting = false;
        $anyPaid = false;
        foreach ($lines as $index => $line) {
            if (! empty($line['done'])) {
                $anyPaid = $anyPaid || ! empty($line['paid']);
                continue;
            }
            $amount = (int) $line['amount'];
            $outcome = app(CampayPayoutService::class)->collectionOutcome($line['reference'], $amount, $line['phone']);
            if ($outcome['state'] === 'paid' && ! empty($outcome['confirmed'])) {
                $bill = ! empty($outcome['bill']) ? (int) $outcome['bill'] : $amount;
                $this->applyPortion(
                    $link,
                    $bill,
                    $line['reference'],
                    $line['phone'],
                    'momo',
                    isset($outcome['fee']) ? (int) $outcome['fee'] : 0,
                    isset($outcome['operator']) ? (string) $outcome['operator'] : ''
                );
                $link = $link->fresh();
                $lines[$index]['done'] = true;
                $lines[$index]['paid'] = true;
                $anyPaid = true;
                continue;
            }
            if ($outcome['state'] === 'failed' && ! empty($outcome['confirmed'])) {
                $lines[$index]['done'] = true;
                $lines[$index]['paid'] = false;
                continue;
            }
            $waiting = true;
        }
        if ($waiting) {
            $cached['lines'] = $lines;
            Cache::put('docpay:'.$link->id, $cached, now()->addDays(7));
            $link->status = 'pending';
            $link->save();

            return;
        }
        Cache::forget('docpay:'.$link->id);
        if ((int) $link->amount <= 0) {
            return;
        }
        $link->campay_reference = null;
        $link->status = $anyPaid ? 'waiting' : 'failed';
        $link->error = $anyPaid ? null : 'The payment was not approved.';
        $link->save();
    }

    protected function applyPortion(DocumentPaymentLink $link, $amount, $reference, $phone, $method, $fee = 0, $operator = '')
    {
        $amount = (int) $amount;
        $reference = (string) $reference;
        if ($amount < 1 || ($reference !== '' && Cache::has('docpay-done:'.$reference))) {
            return;
        }
        if ($reference !== '') {
            Cache::put('docpay-done:'.$reference, 1, now()->addDays(14));
        }
        $amount = min($amount, (int) $link->amount);
        if ($amount < 1) {
            return;
        }
        $phone = $phone ?: $link->phone;
        $wasVisa = $method === 'visa';
        if ($link->kind === 'sale') {
            app(CloudTenantContext::class)->withoutIsolation(function () use ($link, $amount, $reference, $wasVisa) {
                $sale = Sale::find($link->document_id);
                if (! $sale) {
                    return;
                }
                if ($reference !== '' && Payment::where('sale_id', $sale->id)->where('payment_note', 'Campay '.$reference)->exists()) {
                    return;
                }
                $sale->paid_amount = (float) $sale->paid_amount + $amount;
                $balance = (float) $sale->grand_total - (float) $sale->paid_amount;
                $sale->payment_status = $balance <= 0 ? 4 : ($sale->paid_amount > 0 ? 3 : 2);
                $sale->save();
                $account = Account::where('is_default', true)->first();
                $payment = new Payment();
                $payment->sale_id = $sale->id;
                $payment->user_id = $sale->user_id;
                if ($account) {
                    $payment->account_id = $account->id;
                }
                if ($sale->cloud_tenant_id) {
                    $payment->setAttribute('cloud_tenant_id', $sale->cloud_tenant_id);
                }
                $payment->amount = $amount;
                $payment->change = 0;
                $payment->paying_method = $wasVisa ? 'Credit Card' : 'Mobile Money';
                $payment->payment_reference = 'spr-'.date('Ymd').'-'.date('His');
                $payment->payment_note = $reference !== '' ? 'Campay '.$reference : 'Paid from the payment link';
                $payment->save();
            });
        }
        $left = (int) $link->amount - $amount;
        $link->amount = max(0, $left);
        $link->campay_reference = null;
        $link->payment_link = null;
        $link->error = null;
        $finished = $link->amount <= 0;
        $link->status = $finished ? 'paid' : 'waiting';
        $link->save();
        $label = $this->label($link);
        $invoiceTotal = null;
        if ($link->kind === 'sale') {
            app(CloudTenantContext::class)->withoutIsolation(function () use ($link, &$invoiceTotal) {
                $sale = Sale::find($link->document_id);
                if ($sale) {
                    $invoiceTotal = (float) $sale->grand_total;
                }
            });
        }
        $requester = trim((string) $link->requested_by);
        $requesterPhone = '';
        if ($requester !== '') {
            $staff = \App\User::query()->where('is_active', 1)->where('name', $requester)->first();
            if ($staff && method_exists($staff, 'whatsappPhone')) {
                $requesterPhone = (string) $staff->whatsappPhone();
            }
        }
        app(\App\Services\MomoMiniReceipt::class)->send([
            'front' => true,
            'party' => 'CLIENT',
            'description' => 'Payment',
            'phone' => $phone,
            'client_phone' => $phone,
            'name' => $link->person_name,
            'amount' => $amount,
            'fee' => (int) $fee,
            'operator' => (string) $operator,
            'pending' => max(0, (int) $link->amount),
            'invoice_total' => $invoiceTotal,
            'reason' => $label.' payment',
            'reference' => $link->reference_no,
            'date' => now(),
            'method' => $wasVisa ? 'VISA' : 'Mobile Money',
            'requested_by' => $requester,
            'requested_phone' => $requesterPhone,
            'created_by' => $requester,
        ]);
        if ($finished) {
            try {
                app(\App\Services\CompanyReviewInviteService::class)->sendForDocumentPayment($link);
            } catch (\Throwable $e) {
                \Log::warning('Review invite failed', ['error' => $e->getMessage()]);
            }
        }
    }

    protected function find($token)
    {
        $link = DocumentPaymentLink::where('token', $token)->first();
        if (! $link) {
            abort(404);
        }

        return $link;
    }

    protected function label(DocumentPaymentLink $link)
    {
        return $link->kind === 'sale' ? 'Invoice' : 'Quotation';
    }

    protected function guard()
    {
        $role = Auth::user() ? (int) Auth::user()->role_id : 0;
        if ($role !== 1 && $role !== 2) {
            abort(403);
        }
    }
}
