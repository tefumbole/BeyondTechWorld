<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsBylawVersion extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_bylaw_versions';

    protected $guarded = ['id'];
}
