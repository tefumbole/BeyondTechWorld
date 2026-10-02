<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

class CloudBillingEvent extends Model
{
    protected $table = 'cloud_billing_events';

    protected $fillable = [
        'provider',
        'event_id',
        'cloud_subscription_payment_id',
        'outcome',
    ];
}
