<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsEvent extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_events';

    protected $guarded = ['id'];
}
