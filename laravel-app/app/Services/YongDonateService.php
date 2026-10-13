<?php

namespace App\Services;

use App\Support\WhatsAppPhone;
use App\YongDonation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class YongDonateService
{
    public function resolveName($countryCode, $phone)
    {
        $full = $this->phone($countryCode, $phone);
        $digits = preg_replace('/\D/', '', $full);
        if ($this->isCameroon($countryCode, $digits)) {
            $name = $this->campayName($digits);

            return [
                'ok' => true,
                'found' => $name !== '',
                'name' => $name,
                'source' => $name !== '' ? 'campay' : null,
            ];
        }

        $name = '';
        try {
            $name = trim((string) app(BeyondWasenderService::class)->getContactName($digits));
        } catch (\Throwable $e) {
            $name = '';
        }

        return [
            'ok' => true,
            'found' => $name !== '',
            'name' => $name,
            'source' => $name !== '' ? 'whatsapp' : null,
        ];
    }

    public function start($countryCode, $phone, $name, $amount, $method)
    {
        $full = $this->phone($countryCode, $phone);
        $digits = preg_replace('/\D/', '', $full);
        $amount = (int) $amount;
        if ($amount < 100) {
            throw new \InvalidArgumentException('Enter an amount of at least 100 XAF.');
        }
        $method = strtolower((string) $method);
        if (! in_array($method, ['orange', 'mtn', 'visa'], true)) {
            throw new \InvalidArgumentException('Choose Orange, MTN, or Visa.');
        }
        $cameroon = $this->isCameroon($countryCode, $digits);
        if ($method !== 'visa' && ! $cameroon) {
            throw new \InvalidArgumentException('Orange and MTN are for Cameroon numbers. Use Visa for this number.');
        }
        if ($method !== 'visa') {
            $operator = $this->operator($digits);
            if ($operator !== '' && $operator !== $method) {
                $label = $operator === 'mtn' ? 'MTN' : 'Orange';
                throw new \InvalidArgumentException('This number belongs to '.$label.'. Choose '.$label.' to pay.');
            }
        }

        $resolved = trim((string) $name);
        $source = $cameroon ? 'campay' : 'whatsapp';
        if ($resolved === '') {
            $hit = $this->resolveName($countryCode, $phone);
            $resolved = isset($hit['name']) ? trim((string) $hit['name']) : '';
            if (! empty($hit['source'])) {
                $source = $hit['source'];
            }
        }
        if ($resolved === '') {
            throw new \InvalidArgumentException('We could not find a name for this number.');
        }

        $row = new YongDonation();
        $row->id = (string) Str::uuid();
        $row->phone = $full;
        $row->name = $resolved;
        $row->name_source = $source;
        $row->amount = $amount;
        $row->method = $method;
        $row->status = 'pending';
        $row->save();

        if ($method === 'visa') {
            return $this->stripeCheckout($row);
        }

        return $this->campayLink($row);
    }

    public function handleCampay($status, $reference, $externalReference)
    {
        $row = YongDonation::find((string) $externalReference);
        if (! $row) {
            return url('/yong?donate=failed');
        }
        $status = strtoupper((string) $status);
        if ($status === 'SUCCESSFUL') {
            $row->status = 'paid';
            $row->campay_reference = $reference;
            $row->paid_at = now();
            $row->save();

            return url('/yong?donate=ok');
        }
        if ($status === 'PENDING') {
            $row->campay_reference = $reference;
            $row->save();

            return url('/yong?donate=pending');
        }
        $row->status = 'failed';
        $row->campay_reference = $reference;
        $row->save();

        return url('/yong?donate=failed');
    }

    public function handleStripe($sessionId)
    {
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret || ! $sessionId) {
            return url('/yong?donate=failed');
        }
        Stripe::setApiKey($secret);
        try {
            $session = StripeSession::retrieve($sessionId);
        } catch (\Throwable $e) {
            Log::info('Yong donation Stripe retrieve failed: '.$e->getMessage());

            return url('/yong?donate=failed');
        }
        $id = ! empty($session->metadata->donation_id) ? (string) $session->metadata->donation_id : '';
        $row = $id !== '' ? YongDonation::find($id) : YongDonation::where('stripe_session_id', $sessionId)->first();
        if (! $row) {
            return url('/yong?donate=failed');
        }
        $paid = ($session->payment_status === 'paid') || ($session->status === 'complete');
        if (! $paid) {
            return url('/yong?donate=pending');
        }
        $row->status = 'paid';
        $row->stripe_session_id = $sessionId;
        $row->paid_at = now();
        $row->save();

        return url('/yong?donate=ok');
    }

    protected function campayLink(YongDonation $row)
    {
        $token = config('services.campay.token') ?: getenv('CAMPAY_TOKEN') ?: getenv('MOMO_TOKEN');
        if (! $token) {
            throw new \RuntimeException('Mobile money is not configured.');
        }
        $callback = route('yong.donate.payment');
        $payload = json_encode([
            'amount' => (string) (int) $row->amount,
            'from' => preg_replace('/\D/', '', (string) $row->phone),
            'currency' => 'XAF',
            'description' => $row->method === 'orange' ? 'Orange Money donation' : 'MTN MoMo donation',
            'external_reference' => $row->id,
            'redirect_url' => $callback,
            'payment_options' => 'MOMO',
            'failure_redirect_url' => $callback,
        ]);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://www.campay.net/api/get_payment_link/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token '.$token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $raw = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new \RuntimeException('Could not start payment.');
        }
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded) || empty($decoded['link'])) {
            Log::info('Yong donation Campay link failed', ['body' => substr((string) $raw, 0, 400)]);
            throw new \RuntimeException('Could not start the mobile money payment.');
        }

        return $decoded['link'];
    }

    protected function stripeCheckout(YongDonation $row)
    {
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret) {
            throw new \RuntimeException('Card payment is not configured.');
        }
        Stripe::setApiKey($secret);
        $session = StripeSession::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'xaf',
                    'unit_amount' => (int) $row->amount,
                    'product_data' => [
                        'name' => 'Donation — Rev. Yong induction',
                        'description' => $row->name,
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('yong.donate.stripe', [], true).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => url('/yong?donate=failed'),
            'metadata' => [
                'donation_id' => (string) $row->id,
                'module' => 'yong_donate',
            ],
        ]);
        $row->stripe_session_id = $session->id;
        $row->save();

        return $session->url;
    }

    protected function campayName($digits)
    {
        try {
            $hit = app(MobileMoneyHolderService::class)->lookup($digits);
        } catch (\Throwable $e) {
            return '';
        }
        if (! is_array($hit) || (isset($hit['source']) ? (string) $hit['source'] : '') !== 'campay') {
            return '';
        }

        return isset($hit['name']) ? trim((string) $hit['name']) : '';
    }

    protected function phone($countryCode, $phone)
    {
        try {
            return WhatsAppPhone::combine($countryCode, $phone);
        } catch (\Throwable $e) {
            $digits = preg_replace('/\D/', '', (string) $countryCode.(string) $phone);
            if (strlen($digits) < 8) {
                throw new \InvalidArgumentException('Enter a valid phone number.');
            }

            return '+'.$digits;
        }
    }

    protected function isCameroon($countryCode, $digits)
    {
        $code = preg_replace('/\D/', '', (string) $countryCode);
        if ($code === '237' || strpos($digits, '237') === 0) {
            return true;
        }

        return $code === '' && strlen($digits) === 9 && isset($digits[0]) && $digits[0] === '6';
    }

    protected function operator($digits)
    {
        $national = (strpos($digits, '237') === 0) ? substr($digits, 3) : $digits;
        if (strlen($national) < 9) {
            return '';
        }
        $two = substr($national, 0, 2);
        $three = substr($national, 0, 3);
        $mtn = ['650', '651', '652', '653', '654', '680', '681', '682', '683'];
        if ($two === '67' || in_array($three, $mtn, true)) {
            return 'mtn';
        }
        if ($two === '69' || ($three >= '655' && $three <= '659') || ($three >= '690' && $three <= '699')) {
            return 'orange';
        }

        return '';
    }
}
