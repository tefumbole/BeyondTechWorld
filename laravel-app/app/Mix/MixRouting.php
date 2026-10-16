<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixRouting extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_routings';

    protected $fillable = ['version_id', 'destination', 'label', 'pre_post', 'level_db', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
