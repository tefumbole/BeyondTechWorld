<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixManufacturer extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_manufacturers';

    protected $fillable = ['name', 'slug'];
}
