<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixMasteringPreset extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_mastering_presets';

    protected $fillable = [
        'name', 'slug', 'chain_json', 'target_lufs', 'true_peak_db', 'output_format',
        'sample_rate', 'bit_depth', 'notes', 'published',
    ];

    protected $casts = ['published' => 'boolean'];

    public function chain()
    {
        $decoded = json_decode((string) $this->chain_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
