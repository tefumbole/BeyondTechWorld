<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixPresetVersion extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_preset_versions';

    protected $fillable = ['preset_id', 'version_number', 'label', 'notes', 'input_json', 'created_by'];

    public function preset()
    {
        return $this->belongsTo(MixPreset::class, 'preset_id');
    }

    public function bands()
    {
        return $this->hasMany(MixEqBand::class, 'version_id')->orderBy('band_index');
    }

    public function dynamics()
    {
        return $this->hasMany(MixDynamic::class, 'version_id');
    }

    public function effects()
    {
        return $this->hasMany(MixEffect::class, 'version_id');
    }

    public function routings()
    {
        return $this->hasMany(MixRouting::class, 'version_id');
    }

    public function input()
    {
        $decoded = json_decode((string) $this->input_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function compressor()
    {
        return $this->dynamics->first(function ($row) {
            return $row->kind === 'compressor';
        });
    }

    public function gate()
    {
        return $this->dynamics->first(function ($row) {
            return $row->kind === 'gate';
        });
    }
}
