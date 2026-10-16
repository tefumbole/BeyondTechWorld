<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixEffect extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_effects';

    protected $fillable = ['version_id', 'effect_type', 'send_level', 'return_level', 'params_json'];

    public function params()
    {
        $decoded = json_decode((string) $this->params_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
