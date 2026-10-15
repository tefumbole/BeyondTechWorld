<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class HotspotSession extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_sessions';

    protected $fillable = [
        'router_id', 'subscription_id', 'username', 'mac', 'framed_ip', 'started_at', 'stopped_at',
        'bytes_in', 'bytes_out', 'acct_session_id', 'uptime_seconds',
    ];

    protected $dates = ['started_at', 'stopped_at'];

    public function router()
    {
        return $this->belongsTo(HotspotRouter::class, 'router_id');
    }

    public function subscription()
    {
        return $this->belongsTo(HotspotSubscription::class, 'subscription_id');
    }

    public function cloudTenantRelations()
    {
        return [
            'router_id' => HotspotRouter::class,
            'subscription_id' => HotspotSubscription::class,
        ];
    }
}
