<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsAuditLog extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_audit_logs';

    protected $guarded = ['id'];
}
