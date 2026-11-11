<?php

namespace App\Antivirus;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class AntivirusSetting extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'antivirus_settings';

    protected $guarded = ['cloud_tenant_id'];
}
