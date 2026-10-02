<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * A subscription charge. Status becomes PAID only after the existing MoMo or VISA provider confirms it.
 */
class CloudSubscriptionPayment extends Model
{
    protected $table = 'cloud_subscription_payments';

    const PENDING = 'PENDING';
    const PAID = 'PAID';
    const FAILED = 'FAILED';
    const RECONCILE = 'RECONCILE';

    protected $fillable = [
        'cloud_tenant_id',
        'cloud_subscription_id',
        'method_code',
        'amount',
        'currency',
        'provider',
        'provider_reference',
        'status',
        'paid_at',
    ];

    protected $dates = [
        'paid_at',
    ];

    public function cloudTenant()
    {
        return $this->belongsTo(CloudTenant::class, 'cloud_tenant_id');
    }

    public function subscription()
    {
        return $this->belongsTo(CloudSubscription::class, 'cloud_subscription_id');
    }
}
