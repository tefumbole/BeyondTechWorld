<?php

namespace App\Services\Cloud;

use App\Cloud\CloudBillingEvent;
use App\Cloud\CloudBillingInterval;
use App\Cloud\CloudModuleTrial;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionEvent;
use App\Cloud\CloudSubscriptionEventType;
use App\Cloud\CloudSubscriptionNotice;
use App\Cloud\CloudSubscriptionPayment;
use App\Cloud\CloudSubscriptionPaymentItem;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantType;
use App\Cloud\CloudTrialUnit;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Authoritative subscription lifecycle. Controllers call this instead of setting status themselves.
 */
class CloudSubscriptionService
{
    public function startTrial(CloudTenant $tenant, CloudPlan $plan, $actorUserId = null)
    {
        if ($tenant->type === CloudTenantType::INTERNAL) {
            throw new \RuntimeException('An internal company is entitled by the platform. A trial subscription is not created.');
        }
        $plan->load('module');
        $module = $plan->module;
        if (! $module || ! $plan->active || ! $module->active) {
            throw new \RuntimeException('That subscription is not available.');
        }
        $open = CloudSubscription::where('cloud_tenant_id', $tenant->id)
            ->where('cloud_plan_id', $plan->id)
            ->whereIn('status', [CloudSubscriptionStatus::TRIALING, CloudSubscriptionStatus::ACTIVE])
            ->first();
        if ($open) {
            return $open;
        }
        if ($this->introductoryTrialUsed($tenant, $module->id)) {
            throw new \RuntimeException('This company has already used the introductory trial for this module.');
        }

        $gate = app(CloudTrialEligibility::class);
        if ($gate->alreadyUsed($tenant->phone, $module)) {
            throw new \RuntimeException('This phone number has already used the trial for this module.');
        }

        return DB::transaction(function () use ($tenant, $plan, $module, $gate, $actorUserId) {
            $start = now();
            $end = $this->trialEnd($plan, $start);
            $subscription = CloudSubscription::create([
                'cloud_tenant_id' => $tenant->id,
                'cloud_plan_id' => $plan->id,
                'status' => CloudSubscriptionStatus::TRIALING,
                'quoted_price' => $plan->price,
                'quoted_currency' => $plan->currency ?: 'XAF',
                'trial_started_at' => $start,
                'trial_ends_at' => $end,
                'current_period_start' => $start,
                'current_period_end' => $end,
                'cancel_at_period_end' => false,
            ]);
            $this->rememberTrial($tenant, $module->id, CloudModuleTrial::INTRODUCTORY, $start, $end, $actorUserId);
            $gate->claim($tenant->phone, $module, $tenant);
            $this->record($tenant->id, $subscription->id, CloudSubscriptionEventType::TRIAL_STARTED, $actorUserId, [
                'module' => $module->code,
                'trial_ends_at' => $end->toDateTimeString(),
            ]);
            $this->record($tenant->id, $subscription->id, CloudSubscriptionEventType::SUBSCRIPTION_CREATED, $actorUserId, [
                'module' => $module->code,
            ]);

            return $subscription;
        });
    }

    public function grantTrial(CloudTenant $tenant, CloudPlan $plan, $actorUserId)
    {
        $this->assertCustomer($tenant);
        $plan->load('module');
        if (! $plan->module) {
            throw new \RuntimeException('That subscription is not available.');
        }
        $start = now();
        $end = $this->trialEnd($plan, $start);
        $subscription = CloudSubscription::where('cloud_tenant_id', $tenant->id)
            ->where('cloud_plan_id', $plan->id)
            ->orderByDesc('id')
            ->first();
        if (! $subscription) {
            $subscription = new CloudSubscription([
                'cloud_tenant_id' => $tenant->id,
                'cloud_plan_id' => $plan->id,
                'quoted_price' => $plan->price,
                'quoted_currency' => $plan->currency ?: 'XAF',
            ]);
        }
        $subscription->status = CloudSubscriptionStatus::TRIALING;
        $subscription->trial_started_at = $start;
        $subscription->trial_ends_at = $end;
        $subscription->current_period_start = $start;
        $subscription->current_period_end = $end;
        $subscription->cancel_at_period_end = false;
        $subscription->cancelled_at = null;
        $subscription->suspended_at = null;
        $subscription->save();
        $this->rememberTrial($tenant, $plan->module->id, CloudModuleTrial::ADMIN, $start, $end, $actorUserId);
        $this->record($tenant->id, $subscription->id, CloudSubscriptionEventType::TRIAL_EXTENDED, $actorUserId, [
            'module' => $plan->module->code,
        ]);
        $this->record($tenant->id, $subscription->id, CloudSubscriptionEventType::ADMIN_OVERRIDE, $actorUserId, [
            'action' => 'grant_trial',
        ]);

        return $subscription;
    }

    public function extendSubscription(CloudSubscription $subscription, $actorUserId)
    {
        $subscription->load('plan');
        $start = now();
        if ($subscription->status === CloudSubscriptionStatus::ACTIVE && $subscription->current_period_end && $subscription->current_period_end->gt($start)) {
            $start = $subscription->current_period_end->copy();
        }
        $subscription->status = CloudSubscriptionStatus::ACTIVE;
        $subscription->current_period_start = $subscription->current_period_end && $subscription->current_period_end->gt(now())
            ? $subscription->current_period_start
            : now();
        $subscription->current_period_end = $this->periodEnd($subscription->plan, $start);
        $subscription->cancel_at_period_end = false;
        $subscription->cancelled_at = null;
        $subscription->suspended_at = null;
        $subscription->save();
        $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::ADMIN_OVERRIDE, $actorUserId, [
            'action' => 'extend_subscription',
            'current_period_end' => $subscription->current_period_end->toDateTimeString(),
        ]);

        return $subscription;
    }

    public function suspend(CloudSubscription $subscription, $actorUserId)
    {
        $subscription->status = CloudSubscriptionStatus::SUSPENDED;
        $subscription->suspended_at = now();
        $subscription->save();
        $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::SUSPENDED, $actorUserId, []);

        return $subscription;
    }

    public function reactivate(CloudSubscription $subscription, $actorUserId)
    {
        if ($subscription->current_period_end && $subscription->current_period_end->gt(now())) {
            $subscription->status = CloudSubscriptionStatus::ACTIVE;
        } else {
            $subscription->status = CloudSubscriptionStatus::PAST_DUE;
        }
        $subscription->suspended_at = null;
        $subscription->save();
        $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::ADMIN_OVERRIDE, $actorUserId, [
            'action' => 'reactivate',
            'status' => $subscription->status,
        ]);

        return $subscription;
    }

    public function cancelNow(CloudSubscription $subscription, $actorUserId = null)
    {
        $subscription->status = CloudSubscriptionStatus::CANCELLED;
        $subscription->cancelled_at = now();
        $subscription->cancel_at_period_end = false;
        $subscription->save();
        $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::CANCELLED, $actorUserId, [
            'when' => 'now',
        ]);

        return $subscription;
    }

    public function cancelAtPeriodEnd(CloudSubscription $subscription, $actorUserId = null)
    {
        $subscription->cancel_at_period_end = true;
        $subscription->save();
        $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::CANCELLED, $actorUserId, [
            'when' => 'period_end',
        ]);

        return $subscription;
    }

    /**
     * Provider-confirmed payment. Browser input is ignored.
     * The same provider event id extends a period once.
     *
     * @return array
     */
    public function confirmPayment(CloudSubscriptionPayment $payment, array $proof)
    {
        $provider = isset($proof['provider']) ? (string) $proof['provider'] : (string) $payment->provider;
        $eventId = isset($proof['event_id']) ? (string) $proof['event_id'] : '';
        $status = isset($proof['status']) ? strtolower((string) $proof['status']) : '';
        $amount = isset($proof['amount']) ? (float) $proof['amount'] : null;
        $currency = isset($proof['currency']) ? strtoupper((string) $proof['currency']) : '';
        $tenantId = isset($proof['tenant_id']) ? (int) $proof['tenant_id'] : 0;
        $reference = isset($proof['provider_reference']) ? (string) $proof['provider_reference'] : '';

        $result = DB::transaction(function () use ($payment, $provider, $eventId, $status, $amount, $currency, $tenantId, $reference) {
            if ($eventId !== '' && Schema::hasTable('cloud_billing_events')) {
                $seen = CloudBillingEvent::where('provider', $provider)->where('event_id', $eventId)->first();
                if ($seen) {
                    return ['applied' => false, 'reason' => 'duplicate', 'payment_status' => $payment->fresh()->status];
                }
            }

            $locked = CloudSubscriptionPayment::where('id', $payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return ['applied' => false, 'reason' => 'missing'];
            }
            if ($tenantId !== (int) $locked->cloud_tenant_id) {
                $this->rememberBilling($provider, $eventId, $locked->id, 'denied_tenant');
                $this->record($locked->cloud_tenant_id, $locked->cloud_subscription_id, CloudSubscriptionEventType::PAYMENT_FAILED, null, [
                    'reason' => 'wrong_tenant',
                ]);

                return ['applied' => false, 'reason' => 'wrong_tenant'];
            }

            if ($locked->provider_reference && $reference !== '' && $reference !== (string) $locked->provider_reference) {
                $this->rememberBilling($provider, $eventId, $locked->id, 'denied_reference');
                $this->record($locked->cloud_tenant_id, $locked->cloud_subscription_id, CloudSubscriptionEventType::PAYMENT_FAILED, null, [
                    'reason' => 'wrong_reference',
                ]);

                return ['applied' => false, 'reason' => 'wrong_reference'];
            }

            $expectedCurrency = strtoupper((string) ($locked->currency ?: 'XAF'));
            $expectedAmount = round((float) $locked->amount, 2);
            if ($currency === '' || $currency !== $expectedCurrency) {
                $locked->status = CloudSubscriptionPayment::RECONCILE;
                $locked->save();
                $this->rememberBilling($provider, $eventId, $locked->id, 'wrong_currency');
                $this->record($locked->cloud_tenant_id, $locked->cloud_subscription_id, CloudSubscriptionEventType::PAYMENT_RECONCILE, null, [
                    'reason' => 'wrong_currency',
                    'expected' => $expectedCurrency,
                ]);

                return ['applied' => false, 'reason' => 'wrong_currency'];
            }
            if ($amount === null || round($amount, 2) !== $expectedAmount) {
                $locked->status = CloudSubscriptionPayment::RECONCILE;
                $locked->save();
                $this->rememberBilling($provider, $eventId, $locked->id, 'reconcile');
                $this->record($locked->cloud_tenant_id, $locked->cloud_subscription_id, CloudSubscriptionEventType::PAYMENT_RECONCILE, null, [
                    'expected' => $expectedAmount,
                    'currency' => $expectedCurrency,
                ]);

                return ['applied' => false, 'reason' => 'reconcile'];
            }

            if ($status !== 'paid') {
                if ($locked->status !== CloudSubscriptionPayment::PAID) {
                    $locked->status = CloudSubscriptionPayment::FAILED;
                    $locked->save();
                }
                $this->rememberBilling($provider, $eventId, $locked->id, 'failed');
                $this->record($locked->cloud_tenant_id, $locked->cloud_subscription_id, CloudSubscriptionEventType::PAYMENT_FAILED, null, []);
                $this->notice($locked->cloud_tenant_id, $locked->cloud_subscription_id, CloudSubscriptionNotice::PAYMENT_FAILED);

                return ['applied' => false, 'reason' => 'failed'];
            }

            if ($locked->status === CloudSubscriptionPayment::PAID) {
                $this->rememberBilling($provider, $eventId, $locked->id, 'already_paid');

                return ['applied' => false, 'reason' => 'already_paid', 'payment_status' => $locked->status];
            }

            $locked->status = CloudSubscriptionPayment::PAID;
            $locked->paid_at = now();
            if ($reference !== '') {
                $locked->provider_reference = $reference;
            }
            if (Schema::hasColumn('cloud_subscription_payments', 'confirmation_source')) {
                $locked->confirmation_source = CloudSubscriptionPayment::PROVIDER_CONFIRMED;
            }
            $locked->save();
            $renewed = $this->activateFromPaidPayment($locked);
            $this->rememberBilling($provider, $eventId, $locked->id, $renewed ? 'renewed' : 'activated');

            return ['applied' => true, 'reason' => $renewed ? 'renewed' : 'activated', 'payment_status' => $locked->status];
        });

        if (in_array(isset($result['reason']) ? $result['reason'] : '', ['reconcile', 'wrong_currency'], true)) {
            $this->notifyReview($payment);
        }
        if (! empty($result['applied'])) {
            $this->notifyPaid($payment);
        }

        return $result;
    }

    /**
     * Platform admin records an offline payment. A company owner cannot call this.
     */
    public function recordManualPayment(CloudTenant $tenant, CloudSubscription $subscription, $actorUserId, array $details)
    {
        if ($tenant->type === CloudTenantType::INTERNAL) {
            throw new \RuntimeException('The internal company does not pay a subscription.');
        }
        if ((int) $subscription->cloud_tenant_id !== (int) $tenant->id) {
            throw new \RuntimeException('That subscription is not on this company.');
        }
        $subscription->load('plan');
        $amount = $subscription->quoted_price !== null ? $subscription->quoted_price : $subscription->plan->price;
        $currency = $subscription->quoted_currency ?: $subscription->plan->currency;
        $payment = CloudSubscriptionPayment::create([
            'cloud_tenant_id' => $tenant->id,
            'cloud_subscription_id' => $subscription->id,
            'method_code' => isset($details['method']) ? substr((string) $details['method'], 0, 32) : 'MANUAL',
            'amount' => $amount,
            'currency' => $currency ?: 'XAF',
            'provider' => 'manual',
            'provider_reference' => isset($details['reference']) ? substr((string) $details['reference'], 0, 191) : null,
            'status' => CloudSubscriptionPayment::PAID,
            'paid_at' => now(),
        ]);
        if (Schema::hasColumn('cloud_subscription_payments', 'confirmation_source')) {
            $payment->confirmation_source = CloudSubscriptionPayment::ADMIN_CONFIRMED;
            $payment->internal_reference = 'manual-'.$payment->id;
            $payment->save();
        }
        if (Schema::hasTable('cloud_subscription_payment_items')) {
            CloudSubscriptionPaymentItem::create([
                'cloud_subscription_payment_id' => $payment->id,
                'cloud_tenant_id' => $tenant->id,
                'cloud_subscription_id' => $subscription->id,
                'cloud_plan_id' => $subscription->cloud_plan_id,
                'module_code' => $subscription->plan && $subscription->plan->module ? $subscription->plan->module->code : null,
                'amount' => $amount,
                'currency' => $currency ?: 'XAF',
            ]);
        }
        $this->activateFromPaidPayment($payment);
        $this->record($tenant->id, $subscription->id, CloudSubscriptionEventType::PAYMENT_CONFIRMED, $actorUserId, [
            'source' => CloudSubscriptionPayment::ADMIN_CONFIRMED,
            'reason' => isset($details['reason']) ? (string) $details['reason'] : '',
            'payment_id' => $payment->id,
        ]);

        return $payment->fresh();
    }

    /**
     * A refund does not delete company data or remove a period that was already delivered.
     */
    public function recordRefund(CloudSubscriptionPayment $payment, $actorUserId, $reason)
    {
        if ($payment->status !== CloudSubscriptionPayment::PAID) {
            return $payment;
        }
        $payment->status = CloudSubscriptionPayment::REFUNDED;
        $payment->save();
        $this->record($payment->cloud_tenant_id, $payment->cloud_subscription_id, CloudSubscriptionEventType::PAYMENT_FAILED, $actorUserId, [
            'reason' => 'refund',
            'note' => (string) $reason,
            'payment_id' => $payment->id,
        ]);

        return $payment->fresh();
    }

    public function processDue($at = null)
    {
        $at = $at ?: now();
        $changed = 0;
        if (! Schema::hasTable('cloud_subscriptions')) {
            return 0;
        }

        $trials = CloudSubscription::where('status', CloudSubscriptionStatus::TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $at)
            ->get();
        foreach ($trials as $subscription) {
            $updated = CloudSubscription::where('id', $subscription->id)
                ->where('status', CloudSubscriptionStatus::TRIALING)
                ->update(['status' => CloudSubscriptionStatus::EXPIRED]);
            if ($updated) {
                $changed++;
                $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::TRIAL_EXPIRED, null, []);
                $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::EXPIRED, null, []);
            }
        }

        $ending = CloudSubscription::where('status', CloudSubscriptionStatus::ACTIVE)
            ->where('cancel_at_period_end', 1)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $at)
            ->get();
        foreach ($ending as $subscription) {
            $updated = CloudSubscription::where('id', $subscription->id)
                ->where('status', CloudSubscriptionStatus::ACTIVE)
                ->update([
                    'status' => CloudSubscriptionStatus::CANCELLED,
                    'cancelled_at' => $at,
                ]);
            if ($updated) {
                $changed++;
                $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::CANCELLED, null, [
                    'when' => 'period_end',
                ]);
            }
        }

        $past = CloudSubscription::where('status', CloudSubscriptionStatus::ACTIVE)
            ->where(function ($query) {
                $query->where('cancel_at_period_end', 0)->orWhereNull('cancel_at_period_end');
            })
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $at)
            ->get();
        foreach ($past as $subscription) {
            $updated = CloudSubscription::where('id', $subscription->id)
                ->where('status', CloudSubscriptionStatus::ACTIVE)
                ->update(['status' => CloudSubscriptionStatus::PAST_DUE]);
            if ($updated) {
                $changed++;
                $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::PAST_DUE, null, []);
                $this->notice($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionNotice::PAYMENT_DUE);
            }
        }

        $grace = (int) config('cloud.grace_hours', 0);
        if ($grace > 0) {
            $cutoff = $at->copy()->subHours($grace);
            $over = CloudSubscription::where('status', CloudSubscriptionStatus::PAST_DUE)
                ->whereNotNull('current_period_end')
                ->where('current_period_end', '<=', $cutoff)
                ->get();
            foreach ($over as $subscription) {
                $updated = CloudSubscription::where('id', $subscription->id)
                    ->where('status', CloudSubscriptionStatus::PAST_DUE)
                    ->update(['status' => CloudSubscriptionStatus::EXPIRED]);
                if ($updated) {
                    $changed++;
                    $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::EXPIRED, null, [
                        'from' => 'past_due',
                    ]);
                }
            }
        }

        $this->prepareReminders($at);

        return $changed;
    }

    public function trialEnd(CloudPlan $plan, $start = null)
    {
        $start = $start ? $start->copy() : now();
        $value = max(1, (int) $plan->trial_value);
        if ($plan->trial_unit === CloudTrialUnit::MONTH) {
            return $start->addMonths($value);
        }

        return $start->addHours($value);
    }

    public function periodEnd(CloudPlan $plan, $start)
    {
        $start = $start->copy();
        if ($plan && $plan->billing_interval === CloudBillingInterval::MONTH) {
            return $start->addMonth();
        }

        return $start->addMonth();
    }

    public function introductoryTrialUsed(CloudTenant $tenant, $moduleId)
    {
        if (! Schema::hasTable('cloud_module_trials')) {
            return false;
        }

        return CloudModuleTrial::where('cloud_tenant_id', $tenant->id)
            ->where('cloud_module_id', $moduleId)
            ->where('source', CloudModuleTrial::INTRODUCTORY)
            ->exists();
    }

    protected function activateFromPaidPayment(CloudSubscriptionPayment $payment)
    {
        $ids = [];
        if (Schema::hasTable('cloud_subscription_payment_items')) {
            $ids = CloudSubscriptionPaymentItem::where('cloud_subscription_payment_id', $payment->id)
                ->where('cloud_tenant_id', $payment->cloud_tenant_id)
                ->pluck('cloud_subscription_id')
                ->all();
        }
        if (count($ids) === 0) {
            $ids = [$payment->cloud_subscription_id];
        }
        $renewed = false;
        foreach ($ids as $subscriptionId) {
            $renewed = $this->activateOne((int) $subscriptionId, $payment) || $renewed;
        }

        return $renewed;
    }

    protected function activateOne($subscriptionId, CloudSubscriptionPayment $payment)
    {
        $subscription = CloudSubscription::where('id', $subscriptionId)
            ->where('cloud_tenant_id', $payment->cloud_tenant_id)
            ->lockForUpdate()
            ->first();
        if (! $subscription) {
            return false;
        }
        $subscription->load('plan');
        $paidLeft = in_array($subscription->status, [CloudSubscriptionStatus::ACTIVE, CloudSubscriptionStatus::SUSPENDED], true)
            && $subscription->current_period_end
            && $subscription->current_period_end->gt(now());
        $trialLeft = $subscription->status === CloudSubscriptionStatus::TRIALING
            && $subscription->trial_ends_at
            && $subscription->trial_ends_at->gt(now());
        if ($paidLeft) {
            $start = $subscription->current_period_end->copy();
        } elseif ($trialLeft) {
            $start = $subscription->trial_ends_at->copy();
            $subscription->current_period_start = $start->copy();
        } else {
            $start = now();
            $subscription->current_period_start = $start->copy();
        }
        $subscription->status = CloudSubscriptionStatus::ACTIVE;
        $subscription->current_period_end = $this->periodEnd($subscription->plan, $start);
        $subscription->cancel_at_period_end = false;
        $subscription->suspended_at = null;
        $subscription->save();
        $this->record(
            $subscription->cloud_tenant_id,
            $subscription->id,
            $paidLeft ? CloudSubscriptionEventType::SUBSCRIPTION_RENEWED : CloudSubscriptionEventType::SUBSCRIPTION_ACTIVATED,
            null,
            ['current_period_end' => $subscription->current_period_end->toDateTimeString()]
        );
        $this->record($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionEventType::PAYMENT_CONFIRMED, null, [
            'payment_id' => $payment->id,
            'currency' => $payment->currency,
        ]);

        return $paidLeft;
    }

    protected function notifyPaid(CloudSubscriptionPayment $payment)
    {
        try {
            $payment = $payment->fresh();
            $tenant = CloudTenant::find($payment->cloud_tenant_id);
            $body = 'Beyond Cloud payment '.$payment->internal_reference.' is confirmed for '.($tenant ? $tenant->name : 'a company').'.';
            $admins = User::where('role_id', 1)->where('is_active', 1)->pluck('email')->filter()->all();
            if ($admins) {
                Mail::raw($body, function ($message) use ($admins) {
                    $message->to($admins)->subject('Beyond Cloud payment confirmed');
                });
            }
        } catch (\Exception $e) {
            // A mail failure must not undo a confirmed payment.
        }
    }

    protected function notifyReview(CloudSubscriptionPayment $payment)
    {
        try {
            $admins = User::where('role_id', 1)->where('is_active', 1)->pluck('email')->filter()->all();
            if (! $admins) {
                return;
            }
            Mail::raw('Beyond Cloud payment '.$payment->id.' needs review. It was not activated.', function ($message) use ($admins) {
                $message->to($admins)->subject('Beyond Cloud payment needs review');
            });
        } catch (\Exception $e) {
        }
    }

    protected function rememberTrial(CloudTenant $tenant, $moduleId, $source, $start, $end, $actorUserId)
    {
        if (! Schema::hasTable('cloud_module_trials')) {
            return;
        }
        CloudModuleTrial::create([
            'cloud_tenant_id' => $tenant->id,
            'cloud_module_id' => $moduleId,
            'source' => $source,
            'normalized_phone' => app(CloudTrialEligibility::class)->normalizePhone($tenant->phone),
            'started_at' => $start,
            'ends_at' => $end,
            'actor_user_id' => $actorUserId,
        ]);
    }

    protected function record($tenantId, $subscriptionId, $event, $actorUserId, array $payload)
    {
        if (! Schema::hasTable('cloud_subscription_events')) {
            return;
        }
        CloudSubscriptionEvent::create([
            'cloud_tenant_id' => $tenantId,
            'cloud_subscription_id' => $subscriptionId,
            'event' => $event,
            'actor_user_id' => $actorUserId,
            'payload' => $payload ? json_encode($payload) : null,
        ]);
    }

    protected function rememberBilling($provider, $eventId, $paymentId, $outcome)
    {
        if ($eventId === '' || ! Schema::hasTable('cloud_billing_events')) {
            return;
        }
        CloudBillingEvent::create([
            'provider' => $provider,
            'event_id' => $eventId,
            'cloud_subscription_payment_id' => $paymentId,
            'outcome' => $outcome,
        ]);
    }

    protected function notice($tenantId, $subscriptionId, $kind)
    {
        if (! $subscriptionId || ! Schema::hasTable('cloud_subscription_notices')) {
            return;
        }
        $exists = CloudSubscriptionNotice::where('cloud_subscription_id', $subscriptionId)->where('kind', $kind)->exists();
        if ($exists) {
            return;
        }
        CloudSubscriptionNotice::create([
            'cloud_tenant_id' => $tenantId,
            'cloud_subscription_id' => $subscriptionId,
            'kind' => $kind,
            'created_at' => now(),
        ]);
    }

    protected function prepareReminders($at)
    {
        $soonTrial = CloudSubscription::where('status', CloudSubscriptionStatus::TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', $at)
            ->where('trial_ends_at', '<=', $at->copy()->addHours(2))
            ->get();
        foreach ($soonTrial as $subscription) {
            $this->notice($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionNotice::TRIAL_ENDING);
        }
        $soonPaid = CloudSubscription::where('status', CloudSubscriptionStatus::ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '>', $at)
            ->where('current_period_end', '<=', $at->copy()->addDays(3))
            ->get();
        foreach ($soonPaid as $subscription) {
            $this->notice($subscription->cloud_tenant_id, $subscription->id, CloudSubscriptionNotice::SUBSCRIPTION_EXPIRING);
        }
    }

    protected function assertCustomer(CloudTenant $tenant)
    {
        if ($tenant->type === CloudTenantType::INTERNAL) {
            throw new \RuntimeException('An internal company is not billed with a trial.');
        }
    }
}
