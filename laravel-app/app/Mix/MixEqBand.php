<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixEqBand extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_eq_bands';

    protected $fillable = [
        'version_id', 'band_index', 'enabled', 'filter_type', 'frequency_hz', 'gain_db', 'q',
    ];

    protected $casts = ['enabled' => 'boolean'];
}
