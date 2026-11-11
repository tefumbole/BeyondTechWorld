<?php

namespace App\Antivirus;

use App\Services\Cloud\BelongsToCloudTenant;
use App\Support\AntivirusCatalog;
use Illuminate\Database\Eloquent\Model;

class AntivirusApprover extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'antivirus_approvers';

    protected $guarded = ['cloud_tenant_id'];

    protected $dates = [
        'name_confirmed_at',
        'link_expires_at',
        'phone_verified_at',
        'signed_at',
        'declined_at',
    ];

    public function requestRow()
    {
        return $this->belongsTo(AntivirusRequest::class, 'antivirus_request_id');
    }

    public function roleLabel()
    {
        return AntivirusCatalog::roleLabel('approver', $this->role, $this->role_other);
    }
}
