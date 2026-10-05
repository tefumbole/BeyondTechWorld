<?php

namespace App\Nbc;

use Illuminate\Database\Eloquent\Model;

class NbcMember extends Model
{
    protected $table = 'nbc_members';

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'role', 'part', 'about', 'status',
        'permissions', 'bylaws_version', 'agreed_at', 'signature',
    ];

    protected $hidden = ['password', 'signature'];

    protected $dates = ['agreed_at'];

    public function permissionList()
    {
        if ($this->permissions) {
            $decoded = json_decode($this->permissions, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return NbcAccess::defaults($this->role);
    }

    public function allows($key)
    {
        return NbcAccess::allows($this, $key);
    }
}
