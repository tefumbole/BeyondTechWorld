<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsDelivery extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_deliveries';

    protected $guarded = ['id'];
}
