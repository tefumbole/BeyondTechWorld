<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotVoucher extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_vouchers';

    protected $fillable = [
        'package_id', 'batch_id', 'code', 'state', 'sold_at', 'redeemed_at', 'subscription_id', 'expires_at',
    ];

    protected $dates = ['sold_at', 'redeemed_at', 'expires_at'];

    public function package()
    {
        return $this->belongsTo(HotspotPackage::class, 'package_id');
    }

    public function batch()
    {
        return $this->belongsTo(HotspotVoucherBatch::class, 'batch_id');
    }

    public function cloudTenantRelations()
    {
        return [
            'package_id' => HotspotPackage::class,
            'subscription_id' => HotspotSubscription::class,
        ];
    }
}
