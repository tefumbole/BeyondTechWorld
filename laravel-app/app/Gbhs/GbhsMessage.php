<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsMessage extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_messages';

    protected $guarded = ['id'];
}
