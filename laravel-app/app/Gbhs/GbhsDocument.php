<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsDocument extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_documents';

    protected $guarded = ['id'];
}
