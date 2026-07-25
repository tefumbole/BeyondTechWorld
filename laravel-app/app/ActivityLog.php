<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'user_id', 'user_name', 'user_role', 'action', 'entity', 'entity_id',
        'summary', 'metadata', 'ip_address', 'method', 'path', 'status_code', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function actionBadgeClass()
    {
        $map = [
            'view' => 'badge-info',
            'navigate' => 'badge-info',
            'click' => 'badge-secondary',
            'create' => 'badge-success',
            'update' => 'badge-primary',
            'delete' => 'badge-danger',
            'action' => 'badge-warning',
            'login' => 'badge-dark',
            'logout' => 'badge-dark',
            'failed_login' => 'badge-danger',
        ];

        return $map[$this->action] ?? 'badge-light';
    }
}
