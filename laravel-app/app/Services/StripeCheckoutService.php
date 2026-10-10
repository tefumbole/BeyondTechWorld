<?php

namespace App\Services;

use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class StripeCheckoutService
{
    public function secret()
    {
        return trim((string) config('services.stripe.secret'));
    }

    public function publishable()
    {
        return trim((string) config('services.stripe.key'));
    }

    public function checkout($title, $amount, $successUrl, $cancelUrl, array $metadata = [])
    {
        $secret = $this->secret();
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Card payment is not configured.'];
        }
        $amount = (int) $amount;
        if ($amount < 100) {
            return ['ok' => false, 'error' => 'Enter an amount of at least 100 XAF.'];
        }
        Stripe::setApiKey($secret);
        try {
            $session = StripeSession::create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'xaf',
                        'unit_amount' => $amount,
                        'product_data' => [
                            'name' => substr(trim((string) $title), 0, 120) ?: 'Payment',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Stripe checkout failed', ['error' => substr($e->getMessage(), 0, 180)]);

            return ['ok' => false, 'error' => 'VISA could not be opened. Try again.'];
        }

        return [
            'ok' => true,
            'url' => (string) $session->url,
            'id' => (string) $session->id,
        ];
    }

    public function isPaid($sessionId)
    {
        $sessionId = trim((string) $sessionId);
        if (strpos($sessionId, 'cs_') !== 0 || $this->secret() === '') {
            return false;
        }
        Stripe::setApiKey($this->secret());
        try {
            $session = StripeSession::retrieve($sessionId);
        } catch (\Throwable $e) {
            \Log::warning('Stripe status failed', ['error' => substr($e->getMessage(), 0, 180)]);

            return false;
        }

        return isset($session->payment_status) && $session->payment_status === 'paid';
    }
}
