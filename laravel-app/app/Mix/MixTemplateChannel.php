<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixTemplateChannel extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_template_channels';

    protected $fillable = [
        'template_version_id', 'position', 'name', 'instrument_id', 'preset_version_id', 'bus_id', 'stereo_link',
    ];

    public function instrument()
    {
        return $this->belongsTo(MixInstrument::class, 'instrument_id');
    }

    public function presetVersion()
    {
        return $this->belongsTo(MixPresetVersion::class, 'preset_version_id');
    }

    public function bus()
    {
        return $this->belongsTo(MixBus::class, 'bus_id');
    }
}
