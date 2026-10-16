<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixPreset extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_presets';

    protected $fillable = [
        'instrument_id', 'console_id', 'manufacturer_id', 'collection', 'name', 'slug',
        'source_label', 'notes', 'official_url', 'published', 'current_version_id',
    ];

    protected $casts = ['published' => 'boolean'];

    public function instrument()
    {
        return $this->belongsTo(MixInstrument::class, 'instrument_id');
    }

    public function console()
    {
        return $this->belongsTo(MixConsole::class, 'console_id');
    }

    public function manufacturer()
    {
        return $this->belongsTo(MixManufacturer::class, 'manufacturer_id');
    }

    public function versions()
    {
        return $this->hasMany(MixPresetVersion::class, 'preset_id')->orderBy('version_number');
    }

    public function currentVersion()
    {
        return $this->belongsTo(MixPresetVersion::class, 'current_version_id');
    }

    public function sourceName()
    {
        if ($this->collection === 'presonus') {
            return 'PreSonus';
        }

        return 'Beyond';
    }
}
