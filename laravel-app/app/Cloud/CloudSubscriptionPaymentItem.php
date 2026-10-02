<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

class CloudSubscriptionPaymentItem extends Model
{
    protected $fillable = [
        'cloud_subscription_payment_id',
        'cloud_tenant_id',
        'cloud_subscription_id',
        'cloud_plan_id',
        'module_code',
        'amount',
        'currency',
    ];

    public function payment()
    {
        return $this->belongsTo(CloudSubscriptionPayment::class, 'cloud_subscription_payment_id');
    }

    public function subscription()
    {
        return $this->belongsTo(CloudSubscription::class, 'cloud_subscription_id');
    }
}
