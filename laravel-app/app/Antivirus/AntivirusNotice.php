<?php

namespace App\Antivirus;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class AntivirusNotice extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'antivirus_notices';

    protected $guarded = ['cloud_tenant_id'];

    protected $dates = [
        'text_sent_at',
        'document_sent_at',
    ];
}
