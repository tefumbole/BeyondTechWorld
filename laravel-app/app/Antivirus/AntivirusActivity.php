<?php

namespace App\Antivirus;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class AntivirusActivity extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'antivirus_activities';

    protected $guarded = ['cloud_tenant_id'];
}
