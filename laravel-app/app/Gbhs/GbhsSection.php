<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsSection extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_sections';

    protected $guarded = ['id'];
}
