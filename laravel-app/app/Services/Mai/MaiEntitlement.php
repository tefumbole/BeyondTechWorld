<?php

namespace App\Services\Mai;

use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionStatus;
use App\Mai\MaiSession;
use Illuminate\Support\Facades\Schema;

/**
 * Plans do not have a separate API price.
 * Default: sending is allowed when there is no subscription row.
 * A trial or paid row allows sending only until its end date.
 * Expired, cancelled, suspended, and past-due rows do not.
 */
class MaiEntitlement
{
    public static function canSend(MaiSession $session)
    {
        if (! config('cloud.mai_customer.api_sending', true)) {
            return false;
        }
        if (MaiBusinessLine::is($session->phone_number) || MaiBusinessLine::is($session->expected_phone)) {
            return false;
        }
        if (! $session->cloud_tenant_id || ! Schema::hasTable('cloud_subscriptions')) {
            return (bool) config('cloud.mai_customer.api_without_subscription', true);
        }
        $subscription = CloudSubscription::where('cloud_tenant_id', $session->cloud_tenant_id)->orderByDesc('id')->first();
        if (! $subscription) {
            return (bool) config('cloud.mai_customer.api_without_subscription', true);
        }
        if (in_array($subscription->status, [
            CloudSubscriptionStatus::PAST_DUE,
            CloudSubscriptionStatus::SUSPENDED,
            CloudSubscriptionStatus::CANCELLED,
            CloudSubscriptionStatus::EXPIRED,
        ], true)) {
            return false;
        }
        if ($subscription->status === CloudSubscriptionStatus::TRIALING) {
            return ! $subscription->trial_ends_at || $subscription->trial_ends_at->isFuture();
        }
        if ($subscription->status === CloudSubscriptionStatus::ACTIVE) {
            return ! $subscription->current_period_end || $subscription->current_period_end->isFuture();
        }

        return false;
    }

    public static function summary(MaiSession $session)
    {
        $connected = $session->status === 'CONNECTED';

        return [
            'subscription' => self::subscriptionLabel($session),
            'connection' => (string) $session->status,
            'api_sending' => $connected && self::canSend($session),
        ];
    }

    public static function subscriptionLabel(MaiSession $session)
    {
        if (! $session->cloud_tenant_id || ! Schema::hasTable('cloud_subscriptions')) {
            return 'none';
        }
        $subscription = CloudSubscription::where('cloud_tenant_id', $session->cloud_tenant_id)->orderByDesc('id')->first();
        if (! $subscription) {
            return 'none';
        }

        return (string) $subscription->status;
    }
}
