<?php

namespace App\Gbhs;

use App\Account;
use App\Customer;
use App\Deposit;
use App\Services\AccountDepositService;
use App\Services\BinancePayService;
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
        $binance = config('services.binance');
        if (is_array($binance) && trim((string) ($binance['key'] ?? '')) !== '' && trim((string) ($binance['secret'] ?? '')) !== '') {
            $methods['crypto'] = 'Bitcoin';
        }

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
            self::ledger($payment);

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
            self::ledger($payment, $checkout['url']);

            return ['payment' => $payment, 'url' => $checkout['url']];
        }
        if ($payment->method === 'crypto') {
            $result = app(BinancePayService::class)->instructions((int) $payment->amount, $payment->id);
            if (empty($result['ok'])) {
                $payment->status = 'failed';
                $payment->error = isset($result['error']) ? $result['error'] : 'Bitcoin payment could not be opened.';
                $payment->save();
                throw new \RuntimeException($payment->error);
            }
            $payment->provider = 'binance';
            $payment->status = 'pending';
            $payment->provider_reference = json_encode([
                'address' => $result['address'],
                'network' => $result['network'],
                'usdt' => $result['usdt'],
                'coin' => $result['coin'],
            ]);
            $payment->save();
            self::ledger($payment);

            return ['payment' => $payment, 'url' => null];
        }
        throw new \InvalidArgumentException('Choose a payment method.');
    }

    /**
     * A public gift is the same deposit record used by Beyond payments.
     */
    public static function ledger(GbhsPayment $payment, $link = null)
    {
        if ($payment->deposit_id) {
            return Deposit::find($payment->deposit_id);
        }
        $account = Account::where('is_active', 1)->orderBy('is_default', 'desc')->orderBy('id')->first();
        if (! $account) {
            throw new \RuntimeException('Add an active account in Beyond before taking donations.');
        }
        $method = 5;
        if ($payment->method === 'momo') {
            $method = 3;
        } elseif ($payment->method === 'card') {
            $method = 4;
        }
        $customer = self::customer($payment);
        $userId = \App\User::query()->value('id');
        $deposit = Deposit::create([
            'amount' => (int) $payment->amount,
            'customer_id' => $customer ? $customer->id : null,
            'user_id' => $userId ? $userId : 1,
            'account_id' => $account->id,
            'note' => 'GBHS Kumbo donation'.($payment->donor_name ? ' — '.$payment->donor_name : ''),
            'payment_method' => $method,
            'payment_reference' => 'gbhs-'.$payment->id,
            'mtn_number' => $payment->phone,
            'campay_reference' => $payment->method === 'crypto' ? null : $payment->provider_reference,
            'payment_link' => $link,
            'status' => 0,
            'paid_notice' => 0,
        ]);
        $payment->deposit_id = $deposit->id;
        if ($customer && ! $payment->customer_id) {
            $payment->customer_id = $customer->id;
        }
        $payment->save();

        return $deposit;
    }

    public static function noteDepositPaid(Deposit $deposit)
    {
        $payment = GbhsPayment::withoutGlobalScope(\App\Services\Cloud\CloudTenantScope::class)
            ->where('deposit_id', $deposit->id)
            ->first();
        if (! $payment || $payment->status === 'paid') {
            return;
        }
        if ($payment->cloud_tenant_id) {
            $tenant = \App\Cloud\CloudTenant::find($payment->cloud_tenant_id);
            if ($tenant) {
                app(\App\Services\Cloud\CloudTenantContext::class)->set($tenant);
            }
        }
        self::markPaid($payment, null, false);
    }

    protected static function customer(GbhsPayment $payment)
    {
        $phone = preg_replace('/\D/', '', (string) $payment->phone);
        if ($phone === '') {
            return null;
        }
        $tail = substr($phone, -9);
        $customer = Customer::where(function ($query) use ($phone, $tail) {
            $query->where('phone_number', $phone)->orWhere('phone_number', 'like', '%'.$tail);
        })->first();
        if ($customer) {
            return $customer;
        }
        $groupId = \App\CustomerGroup::query()->value('id');

        return Customer::create([
            'customer_group_id' => $groupId ? $groupId : 1,
            'name' => $payment->donor_name ? $payment->donor_name : $phone,
            'phone_number' => $phone,
            'address' => 'N/A',
            'city' => 'N/A',
            'is_active' => 1,
        ]);
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
                self::failDeposit($payment, 'Not approved');

                return 'failed';
            }
            $confirmed = ! empty($outcome['confirmed']) && isset($outcome['state']) && $outcome['state'] === 'paid';
            if (! GbhsRules::paidFromBrowserReturn($confirmed)) {
                return 'pending';
            }
        } elseif ($payment->provider === 'binance') {
            $info = json_decode((string) $payment->provider_reference, true);
            if (! is_array($info) || empty($info['address']) || empty($info['usdt'])) {
                return 'pending';
            }
            $since = $payment->created_at
                ? $payment->created_at->copy()->subMinutes(10)->getTimestamp() * 1000
                : (time() - 3600) * 1000;
            $tx = app(BinancePayService::class)->findPayment($info['address'], $info['usdt'], $since);
            if (! $tx) {
                return 'pending';
            }
            $payment->provider_reference = $tx;
            $payment->save();
        } else {
            return 'pending';
        }

        return self::markPaid($payment);
    }

    protected static function failDeposit(GbhsPayment $payment, $reason)
    {
        if (! $payment->deposit_id) {
            return;
        }
        Deposit::where('id', $payment->deposit_id)->where('status', 0)->update([
            'status' => 2,
            'failure_reason' => substr((string) $reason, 0, 180),
        ]);
    }

    public static function recordBank(GbhsPayment $payment, $userId)
    {
        if ($payment->method !== 'bank') {
            return $payment->status;
        }

        return self::markPaid($payment, $userId);
    }

    protected static function markPaid(GbhsPayment $payment, $userId = null, $creditDeposit = true)
    {
        DB::transaction(function () use ($payment, $userId, $creditDeposit) {
            $locked = GbhsPayment::where('id', $payment->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'paid') {
                return;
            }
            $locked->status = 'paid';
            $locked->error = null;
            $locked->save();
            if ($creditDeposit && $locked->deposit_id) {
                $deposit = Deposit::find($locked->deposit_id);
                if ($deposit && (int) $deposit->status !== 1) {
                    app(AccountDepositService::class)->creditPending($deposit);
                }
            }
            if (! GbhsContribution::where('payment_id', $locked->id)->exists()) {
                $class = $locked->method === 'card' ? 'card' : ($locked->method === 'momo' ? 'momo' : ($locked->method === 'crypto' ? 'crypto' : 'cash'));
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
