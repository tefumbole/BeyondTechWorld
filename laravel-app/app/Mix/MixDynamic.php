<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixDynamic extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_dynamics';

    protected $fillable = [
        'version_id', 'kind', 'enabled', 'threshold_db', 'ratio', 'attack_ms', 'release_ms',
        'knee', 'makeup_db', 'hold_ms', 'range_db', 'key_source', 'key_filter', 'model',
        'expander_mode', 'extra_json',
    ];

    protected $casts = ['enabled' => 'boolean'];

    public function extra()
    {
        $decoded = json_decode((string) $this->extra_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
