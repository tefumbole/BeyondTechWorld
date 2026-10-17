<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsApplication extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_applications';

    protected $guarded = ['id'];
}
