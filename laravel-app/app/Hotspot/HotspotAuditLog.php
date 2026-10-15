<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HotspotAuditLog extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_audit_logs';

    protected $fillable = ['actor_id', 'router_id', 'action', 'summary'];

    public static function write($action, $summary, $routerId = null)
    {
        try {
            $row = new self();
            $row->action = $action;
            $row->summary = substr((string) $summary, 0, 500);
            $row->router_id = $routerId;
            $row->actor_id = Auth::id();
            $row->save();
        } catch (\Throwable $e) {
        }
    }
}
