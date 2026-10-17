<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsLeader extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_leaders';

    protected $guarded = ['id'];
}
