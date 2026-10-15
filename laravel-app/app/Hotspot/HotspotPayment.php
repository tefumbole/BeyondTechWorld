<?php

namespace App\Hotspot;

use App\Customer;
use App\Sale;
use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotPayment extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_payments';

    protected $fillable = [
        'subscription_id', 'customer_id', 'package_id', 'location_id', 'amount', 'currency',
        'method', 'provider', 'provider_reference', 'public_token', 'phone', 'status', 'sale_id', 'paid_at', 'error',
    ];

    protected $dates = ['paid_at'];

    public function subscription()
    {
        return $this->belongsTo(HotspotSubscription::class, 'subscription_id');
    }

    public function package()
    {
        return $this->belongsTo(HotspotPackage::class, 'package_id');
    }

    public function cloudTenantRelations()
    {
        return [
            'subscription_id' => HotspotSubscription::class,
            'customer_id' => Customer::class,
            'package_id' => HotspotPackage::class,
            'location_id' => HotspotLocation::class,
            'sale_id' => Sale::class,
        ];
    }
}
