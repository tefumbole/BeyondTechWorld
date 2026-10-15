<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotVoucherBatch extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_voucher_batches';

    protected $fillable = ['package_id', 'quantity', 'note', 'created_by'];

    public function vouchers()
    {
        return $this->hasMany(HotspotVoucher::class, 'batch_id');
    }

    public function package()
    {
        return $this->belongsTo(HotspotPackage::class, 'package_id');
    }
}
