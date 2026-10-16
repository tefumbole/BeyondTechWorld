<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MixAuditLog extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_audit_logs';

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'body'];

    public static function write($action, $subject, $body = null)
    {
        $log = new static();
        $log->user_id = Auth::id();
        $log->action = $action;
        if ($subject instanceof Model) {
            $log->subject_type = get_class($subject);
            $log->subject_id = $subject->getKey();
        }
        $log->body = $body;
        $log->save();

        return $log;
    }
}
