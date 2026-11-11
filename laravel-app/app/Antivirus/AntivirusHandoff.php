<?php

namespace App\Antivirus;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class AntivirusHandoff extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'antivirus_handoffs';

    protected $guarded = ['cloud_tenant_id'];

    protected $dates = [
        'expires_at',
        'phone_verified_at',
        'consumed_at',
    ];

    public function requestRow()
    {
        return $this->belongsTo(AntivirusRequest::class, 'antivirus_request_id');
    }
}
