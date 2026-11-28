<?php

namespace App\Mai;

use Illuminate\Database\Eloquent\Model;

class MaiApiKey extends Model
{
    protected $table = 'mai_api_keys';

    protected $fillable = [
        'user_id', 'cloud_tenant_id', 'mai_session_id', 'name', 'prefix', 'key_hash', 'scopes', 'revoked_at', 'last_used_at',
    ];

    protected $dates = ['revoked_at', 'last_used_at'];

    public function allows($scope)
    {
        $scopes = array_filter(array_map('trim', explode(',', (string) $this->scopes)));

        return in_array($scope, $scopes, true);
    }
}
