<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

class CloudSubscriptionEvent extends Model
{
    protected $table = 'cloud_subscription_events';

    protected $fillable = [
        'cloud_tenant_id',
        'cloud_subscription_id',
        'event',
        'actor_user_id',
        'payload',
    ];
}
