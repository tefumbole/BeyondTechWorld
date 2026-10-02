<?php

namespace App\Services\Cloud;

use App\Cloud\CloudSubscriptionPayment;

/**
 * Campay collect adapter aligned to the official CamPay Python SDK (1.1.0).
 * A payment-link response is not a collected payment. Tests inject a transport
 * so PHPUnit never calls campay.net.
 */
class CloudCampayBillingProvider implements CloudBillingProviderInterface
{
    protected $token;

    public function provider()
    {
        return 'campay';
    }

    public function begin(CloudSubscriptionPayment $payment)
    {
        $payment->load('cloudTenant');
        $tenant = $payment->cloudTenant;
        $phone = $tenant ? preg_replace('/\D/', '', (string) $tenant->phone) : '';
        $name = $tenant ? trim((string) $tenant->name) : 'Customer';
        $parts = preg_split('/\s+/', $name, 2);
        $body = $this->call('POST', $this->endpoint('get_payment_link/'), [
            'amount' => (string) (int) round((float) $payment->amount),
            'currency' => (string) $payment->currency,
            'description' => 'Beyond Cloud '.($payment->internal_reference ?: $payment->id),
            'external_reference' => $payment->internal_reference ?: ('cloud-'.$payment->id),
            'redirect_url' => route('cloud.pay.return', ['payment' => $payment->id]),
            'failure_redirect_url' => route('cloud.pay.return', ['payment' => $payment->id, 'failed' => 1]),
            'from' => $phone,
            'first_name' => $parts[0],
            'last_name' => isset($parts[1]) && $parts[1] !== '' ? $parts[1] : $parts[0],
            'email' => $tenant && ! empty($tenant->email) ? (string) $tenant->email : '',
            'payment_options' => 'MOMO',
        ]);
        if (! is_array($body) || empty($body['reference']) || empty($body['link'])) {
            return null;
        }
        $payment->provider_reference = (string) $body['reference'];
        $payment->status = CloudSubscriptionPayment::PENDING;
        $payment->save();

        return $body['link'];
    }

    /**
     * Authoritative Campay read: GET /api/transaction/{reference}/
     * The webhook or browser body is not the amount.
     */
    public function status(CloudSubscriptionPayment $payment)
    {
        if (! $payment->provider_reference) {
            return null;
        }
        $body = $this->call('GET', $this->endpoint('transaction/'.$payment->provider_reference.'/'), []);

        return is_array($body) ? $body : null;
    }

    protected function endpoint($path)
    {
        $base = rtrim((string) config('services.campay.base_url'), '/');
        if ($base === '') {
            $base = 'https://www.campay.net/api';
        }

        return $base.'/'.ltrim($path, '/');
    }

    protected function call($method, $url, array $payload)
    {
        $transport = config('services.campay.transport');
        if (is_callable($transport)) {
            return $transport($method, $url, $payload);
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! config('cloud.payments_live') && $host !== 'demo.campay.net') {
            return null;
        }

        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (strpos($url, '/token/') === false) {
            $token = $this->accessToken();
            if (! $token) {
                return null;
            }
            $headers[] = 'Authorization: Token '.$token;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $raw = curl_exec($ch);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function accessToken()
    {
        if ($this->token) {
            return $this->token;
        }
        $username = config('services.campay.username');
        $password = config('services.campay.password');
        if ($username && $password) {
            $body = $this->call('POST', $this->endpoint('token/'), [
                'username' => $username,
                'password' => $password,
            ]);
            if (is_array($body) && ! empty($body['token'])) {
                $this->token = (string) $body['token'];

                return $this->token;
            }

            return null;
        }
        $static = config('services.campay.token');

        return $static ? (string) $static : null;
    }
}
