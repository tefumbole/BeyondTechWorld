<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * One company's subscription to one plan.
 * Phase 1B does not activate a subscription for BeyondTechWorld and does not start a trial clock.
 */
class CloudSubscription extends Model
{
    protected $table = 'cloud_subscriptions';

    protected $fillable = [
        'cloud_tenant_id',
        'cloud_plan_id',
        'status',
        'quoted_price',
        'quoted_currency',
        'payment_method_code',
        'trial_started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'suspended_at',
    ];

    protected $dates = [
        'trial_started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'suspended_at',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function (CloudSubscription $subscription) {
            if (! $subscription->status) {
                $subscription->status = CloudSubscriptionStatus::TRIALING;
            }
        });
    }

    public function cloudTenant()
    {
        return $this->belongsTo(CloudTenant::class, 'cloud_tenant_id');
    }

    public function plan()
    {
        return $this->belongsTo(CloudPlan::class, 'cloud_plan_id');
    }
}
