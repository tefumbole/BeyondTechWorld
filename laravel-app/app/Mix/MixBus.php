<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixBus extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_buses';

    protected $fillable = [
        'name', 'slug', 'bus_type', 'preset_version_id', 'limiter_threshold_db', 'limiter_release_ms', 'notes',
    ];

    public function processing()
    {
        return $this->belongsTo(MixPresetVersion::class, 'preset_version_id');
    }
}
