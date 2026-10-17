<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsMember extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_members';

    protected $guarded = ['id'];
}
