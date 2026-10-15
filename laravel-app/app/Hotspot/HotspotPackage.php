<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotPackage extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_packages';

    protected $fillable = [
        'location_id', 'name', 'price', 'currency', 'validity_seconds', 'data_bytes',
        'rate_up', 'rate_down', 'shared_users', 'simultaneous_sessions', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'price' => 'integer',
        'validity_seconds' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(HotspotLocation::class, 'location_id');
    }

    public function cloudTenantRelations()
    {
        return ['location_id' => HotspotLocation::class];
    }

    public function rateLabel()
    {
        return HotspotLimits::speedLabel($this->rate_up, $this->rate_down);
    }
}
