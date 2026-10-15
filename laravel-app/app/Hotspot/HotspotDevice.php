<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotDevice extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_devices';

    protected $fillable = ['subscription_id', 'mac', 'label', 'last_seen_at'];

    protected $dates = ['last_seen_at'];
}
