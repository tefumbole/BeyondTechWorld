<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsContribution extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_contributions';

    protected $guarded = ['id'];
}
