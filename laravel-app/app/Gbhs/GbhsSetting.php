<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsSetting extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_settings';

    protected $guarded = ['id'];
}
