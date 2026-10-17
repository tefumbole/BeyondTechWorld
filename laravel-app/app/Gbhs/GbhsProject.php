<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsProject extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_projects';

    protected $guarded = ['id'];
}
