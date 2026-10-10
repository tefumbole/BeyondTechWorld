<?php

namespace App\Http\Controllers;

use App\Account;
use App\DocumentPaymentLink;
use App\Payment;
use App\Quotation;
use App\Sale;
use App\Services\CampayPayoutService;
use App\Services\ClientNoticeService;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Messaging\TwilioTemplateSender;
use App\Support\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function requestSale($id)
    {
        $this->guard();
        $document = Sale::with('customer')->findOrFail($id);
        $due = (float) $document->grand_total - (float) $document->paid_amount;
        $amount = (int) round($due);
        if (abs($due - $amount) > 0.001 || $amount < 100) {
            return back()->with('message', 'There is no whole amount of at least 100 XAF left to request.');
        }

        return $this->sendRequest('sale', $document, $amount, 'Invoice');
    }

    public function show($token)
    {
        $link = $this->find($token);
        $this->refresh($link);

        return view('pay.document', ['link' => $link->fresh()]);
    }

    public function pay(Request $request, $token)
    {
        $link = $this->find($token);
        $this->refresh($link);
        $link = $link->fresh();
        if ($link->status === 'paid') {
            return redirect()->route('document.pay', ['token' => $link->token]);
        }
        $method = (string) $request->input('method');
        if (! in_array($method, ['momo', 'visa'], true)) {
            return redirect()->route('document.pay', ['token' => $link->token]);
        }
        $service = app(CampayPayoutService::class);
        $phone = $service->momoNumber($link->phone);
        if ($method === 'momo' && ! $phone) {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'This number is not an MTN or Orange Cameroon number. Pay with VISA.');
        }
        $from = $phone ?: preg_replace('/\D/', '', (string) $link->phone);
        if ($from === '') {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'There is no phone number on this request.');
        }
        $reference = 'doc-'.date('YmdHis').'-'.$link->id.'-'.substr(md5(uniqid('', true)), 0, 6);
        $description = substr($this->label($link).' '.$link->reference_no, 0, 80);
        if ($method === 'momo') {
            $body = $service->collect($from, (int) $link->amount, $reference, $description);
            $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
            if (! is_array($body) || empty($body['reference']) || $status === 'FAILED') {
                $link->status = 'failed';
                $link->error = 'Campay could not ask for the Mobile Money approval.';
                $link->save();

                return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', $link->error);
            }
            $link->method = 'momo';
            $link->campay_reference = (string) $body['reference'];
            $link->status = 'pending';
            $link->error = null;
            $link->save();

            return redirect()->route('document.pay', ['token' => $link->token]);
        }
        $body = $service->cardLink($from, (int) $link->amount, $reference, $description, route('document.pay', ['token' => $link->token]), $link->person_name);
        if (! is_array($body) || empty($body['link'])) {
            return redirect()->route('document.pay', ['token' => $link->token])->with('not_permitted', 'Campay did not return a VISA link.');
        }
        $link->method = 'visa';
        $link->campay_reference = isset($body['reference']) ? (string) $body['reference'] : null;
        $link->payment_link = (string) $body['link'];
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
        $customer = $document->customer;
        if (! $customer || trim((string) $customer->phone_number) === '') {
            return back()->with('not_permitted', 'This client does not have a phone number.');
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
        $url = route('document.pay', ['token' => $link->token]);
        $this->tell($link, $url);

        return back()->with('message', 'Payment link sent to '.$customer->name.'.');
    }

    protected function tell(DocumentPaymentLink $link, $url)
    {
        $label = $this->label($link);
        $result = app(TwilioTemplateSender::class)->sendSharedAction(
            $link->phone,
            $link->person_name,
            WhatsAppMessage::companyName(),
            'pay this '.strtolower($label),
            $link->reference_no ?: $label,
            $url
        );
        if (! empty($result['success'])) {
            return;
        }
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

    protected function refresh(DocumentPaymentLink $link)
    {
        if ($link->status === 'paid' || ! $link->campay_reference) {
            return;
        }
        $body = app(CampayPayoutService::class)->transaction($link->campay_reference);
        $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        if ($status === 'SUCCESSFUL') {
            $this->markPaid($link);
        } elseif ($status === 'FAILED') {
            $link->status = 'failed';
            $link->error = 'The payment was not approved.';
            $link->save();
        }
    }

    protected function markPaid(DocumentPaymentLink $link)
    {
        if ($link->status === 'paid') {
            return;
        }
        $link->status = 'paid';
        $link->error = null;
        $link->save();
        if ($link->kind === 'sale') {
            app(CloudTenantContext::class)->withoutIsolation(function () use ($link) {
                $sale = Sale::find($link->document_id);
                if (! $sale) {
                    return;
                }
                $paid = (float) $sale->paid_amount + (int) $link->amount;
                $sale->paid_amount = $paid;
                $balance = (float) $sale->grand_total - $paid;
                $sale->payment_status = $balance <= 0 ? 4 : 3;
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
                $payment->amount = (int) $link->amount;
                $payment->change = 0;
                $payment->paying_method = $link->method === 'visa' ? 'Credit Card' : 'Mobile Money';
                $payment->payment_reference = 'spr-'.date('Ymd').'-'.date('His');
                $payment->payment_note = 'Paid from the payment link';
                $payment->save();
            });
        }
        $label = $this->label($link);
        $text = 'Dear '.$link->person_name.', a payment of '.number_format($link->amount, 0, '.', ' ').' XAF has been received for '.$label.' '.$link->reference_no.'.';
        app(ClientNoticeService::class)->send($link->phone, $text);
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
