<?php

namespace App\Gbhs;

use App\Services\CampayPayoutService;
use App\Services\StripeCheckoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GbhsDonate
{
    public static function methods()
    {
        $methods = [];
        $campay = config('services.campay');
        if (is_array($campay) && (trim((string) ($campay['token'] ?? '')) !== '' || trim((string) ($campay['username'] ?? '')) !== '')) {
            $methods['momo'] = 'MTN or Orange Mobile Money';
        }
        if (trim((string) config('services.stripe.secret')) !== '') {
            $methods['card'] = 'Visa or Mastercard';
        }
        $methods['bank'] = 'Bank transfer, recorded by the association';

        return $methods;
    }

    public static function start(GbhsPayment $payment)
    {
        if ($payment->method === 'bank') {
            $payment->status = 'awaiting_transfer';
            $payment->provider = 'bank';
            $payment->save();

            return ['payment' => $payment, 'url' => null];
        }
        if ($payment->method === 'momo') {
            $campay = app(CampayPayoutService::class);
            $from = $campay->momoNumber($payment->phone);
            if (! $from) {
                throw new \InvalidArgumentException('This number is not an MTN or Orange Cameroon number. Pay with a card.');
            }
            $payment->provider = 'campay';
            $payment->provider_reference = 'gbhs-'.$payment->id.'-'.Str::random(6);
            $payment->status = 'pending';
            $payment->save();
            $body = $campay->collect($from, (int) $payment->amount, $payment->provider_reference, 'GBHS Kumbo donation');
            $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
            if (! is_array($body) || empty($body['reference']) || $status === 'FAILED') {
                $payment->status = 'failed';
                $payment->error = 'Mobile Money could not be started.';
                $payment->save();
                throw new \RuntimeException($payment->error);
            }
            $payment->provider_reference = (string) $body['reference'];
            $payment->save();

            return ['payment' => $payment, 'url' => null];
        }
        if ($payment->method === 'card') {
            $payment->provider = 'stripe';
            $payment->status = 'pending';
            $payment->save();
            $return = route('gbhs.pay.return', ['token' => $payment->public_token]);
            $checkout = app(StripeCheckoutService::class)->checkout(
                'GBHS Kumbo donation',
                (int) $payment->amount,
                $return,
                $return,
                ['gbhs_payment' => (string) $payment->id]
            );
            if (empty($checkout['ok']) || empty($checkout['id'])) {
                $payment->status = 'failed';
                $payment->error = isset($checkout['error']) ? $checkout['error'] : 'Card checkout could not be opened.';
                $payment->save();
                throw new \RuntimeException($payment->error);
            }
            $payment->provider_reference = (string) $checkout['id'];
            $payment->save();

            return ['payment' => $payment, 'url' => $checkout['url']];
        }
        throw new \InvalidArgumentException('Choose a payment method.');
    }

    /**
     * A browser return never marks a payment paid. Campay status and Stripe
     * session retrieve are the only confirmation paths.
     */
    public static function confirm(GbhsPayment $payment)
    {
        if ($payment->status === 'paid') {
            return 'paid';
        }
        if ($payment->method === 'bank' || $payment->provider === 'bank') {
            return $payment->status;
        }
        $confirmed = false;
        if ($payment->provider === 'stripe') {
            $confirmed = app(StripeCheckoutService::class)->isPaid($payment->provider_reference);
            if (! GbhsRules::paidFromBrowserReturn($confirmed)) {
                return 'pending';
            }
        } elseif ($payment->provider === 'campay') {
            $outcome = app(CampayPayoutService::class)->collectionOutcome(
                $payment->provider_reference,
                $payment->amount,
                $payment->phone
            );
            if (isset($outcome['state']) && $outcome['state'] === 'failed') {
                $payment->status = 'failed';
                $payment->error = 'The payment was not approved.';
                $payment->save();

                return 'failed';
            }
            $confirmed = ! empty($outcome['confirmed']) && isset($outcome['state']) && $outcome['state'] === 'paid';
            if (! GbhsRules::paidFromBrowserReturn($confirmed)) {
                return 'pending';
            }
        } else {
            return 'pending';
        }

        return self::markPaid($payment);
    }

    public static function recordBank(GbhsPayment $payment, $userId)
    {
        if ($payment->method !== 'bank') {
            return $payment->status;
        }

        return self::markPaid($payment, $userId);
    }

    protected static function markPaid(GbhsPayment $payment, $userId = null)
    {
        DB::transaction(function () use ($payment, $userId) {
            $locked = GbhsPayment::where('id', $payment->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'paid') {
                return;
            }
            $locked->status = 'paid';
            $locked->error = null;
            $locked->save();
            if (! GbhsContribution::where('payment_id', $locked->id)->exists()) {
                $class = $locked->method === 'card' ? 'card' : ($locked->method === 'momo' ? 'momo' : 'bank');
                GbhsContribution::create([
                    'batch_id' => $locked->batch_id,
                    'customer_id' => $locked->customer_id,
                    'project_id' => $locked->project_id,
                    'payment_id' => $locked->id,
                    'contributor_name' => $locked->anonymous ? null : $locked->donor_name,
                    'classification' => $class,
                    'amount' => $locked->amount,
                    'verified' => true,
                    'anonymous' => $locked->anonymous,
                    'source_note' => 'Confirmed '.$locked->provider,
                    'verified_by' => $userId,
                    'verified_at' => now(),
                ]);
            }
            GbhsAuditLog::create([
                'actor_id' => $userId,
                'action' => 'payment.confirmed',
                'summary' => $locked->public_token.' '.$locked->amount.' XAF confirmed',
            ]);
        });

        return 'paid';
    }
}
