<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

class CloudSubscriptionNotice extends Model
{
    protected $table = 'cloud_subscription_notices';

    public $timestamps = false;

    const TRIAL_ENDING = 'trial_ending';
    const SUBSCRIPTION_EXPIRING = 'subscription_expiring';
    const PAYMENT_DUE = 'payment_due';
    const PAYMENT_FAILED = 'payment_failed';

    protected $fillable = [
        'cloud_tenant_id',
        'cloud_subscription_id',
        'kind',
        'created_at',
    ];

    protected $dates = [
        'created_at',
    ];
}
