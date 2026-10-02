<?php

namespace App\Services\Cloud;

use App\Cloud\CloudPaymentMethod;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionPayment;
use App\Cloud\CloudSubscriptionPaymentItem;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantType;
use Illuminate\Support\Facades\Schema;

/**
 * Builds an immutable platform billing request from stored plans.
 * The browser does not supply the amount, currency, or tenant.
 */
class CloudBillingCheckout
{
    public function open(CloudTenant $tenant, array $subscriptions, $methodCode)
    {
        if ($tenant->type === CloudTenantType::INTERNAL) {
            throw new \RuntimeException('The internal company does not pay a subscription.');
        }
        $method = CloudPaymentMethod::where('code', strtoupper((string) $methodCode))->where('active', true)->first();
        if (! $method) {
            throw new \RuntimeException('That payment method is not available.');
        }
        if (count($subscriptions) < 1) {
            throw new \RuntimeException('Choose a subscription to pay.');
        }

        $lines = [];
        $total = 0;
        $currency = null;
        foreach ($subscriptions as $subscription) {
            if (! $subscription instanceof CloudSubscription) {
                throw new \RuntimeException('Unknown subscription.');
            }
            if ((int) $subscription->cloud_tenant_id !== (int) $tenant->id) {
                throw new \RuntimeException('That subscription is not on this company.');
            }
            $subscription->load('plan.module');
            $amount = $subscription->quoted_price !== null
                ? $subscription->quoted_price
                : ($subscription->plan ? $subscription->plan->price : null);
            $lineCurrency = $subscription->quoted_currency
                ?: ($subscription->plan ? $subscription->plan->currency : null);
            if ($amount === null || $lineCurrency === null) {
                throw new \RuntimeException('This plan has no price.');
            }
            if ($currency === null) {
                $currency = $lineCurrency;
            }
            if ($currency !== $lineCurrency) {
                throw new \RuntimeException('These modules do not share one currency.');
            }
            $total += (float) $amount;
            $lines[] = [
                'subscription' => $subscription,
                'amount' => number_format((float) $amount, 2, '.', ''),
                'currency' => $lineCurrency,
                'plan_id' => $subscription->cloud_plan_id,
                'module_code' => $subscription->plan && $subscription->plan->module
                    ? $subscription->plan->module->code
                    : null,
            ];
        }

        $payment = CloudSubscriptionPayment::create([
            'cloud_tenant_id' => $tenant->id,
            'cloud_subscription_id' => $lines[0]['subscription']->id,
            'method_code' => $method->code,
            'amount' => number_format($total, 2, '.', ''),
            'currency' => $currency,
            'provider' => $method->provider,
            'status' => CloudSubscriptionPayment::PENDING,
        ]);
        if (Schema::hasColumn('cloud_subscription_payments', 'internal_reference')) {
            $payment->internal_reference = 'btw-'.$payment->id.'-'.substr(sha1((string) $payment->id), 0, 10);
            $payment->snapshot = json_encode([
                'tenant_id' => $tenant->id,
                'currency' => $currency,
                'total' => $payment->amount,
                'lines' => array_map(function ($line) {
                    return [
                        'subscription_id' => $line['subscription']->id,
                        'plan_id' => $line['plan_id'],
                        'module_code' => $line['module_code'],
                        'amount' => $line['amount'],
                        'currency' => $line['currency'],
                    ];
                }, $lines),
            ]);
            $payment->save();
        }
        if (Schema::hasTable('cloud_subscription_payment_items')) {
            foreach ($lines as $line) {
                CloudSubscriptionPaymentItem::create([
                    'cloud_subscription_payment_id' => $payment->id,
                    'cloud_tenant_id' => $tenant->id,
                    'cloud_subscription_id' => $line['subscription']->id,
                    'cloud_plan_id' => $line['plan_id'],
                    'module_code' => $line['module_code'],
                    'amount' => $line['amount'],
                    'currency' => $line['currency'],
                ]);
            }
        }

        return $payment->fresh();
    }
}
