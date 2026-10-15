<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotLocation extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_locations';

    protected $fillable = ['name', 'address', 'timezone', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function routers()
    {
        return $this->hasMany(HotspotRouter::class, 'location_id');
    }

    public function packages()
    {
        return $this->hasMany(HotspotPackage::class, 'location_id');
    }
}
