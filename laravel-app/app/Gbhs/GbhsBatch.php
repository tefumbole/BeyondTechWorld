<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsBatch extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_batches';

    protected $guarded = ['id'];
}
