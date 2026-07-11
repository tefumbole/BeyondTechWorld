<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles;

    protected $fillable = [
        'name', 'email', 'password',"phone", "additional_phone", "company_name", "role_id", "biller_id", "warehouse_id", "is_active", "is_deleted", "sign", "stemp", "approve", "otp", "otp_time", "otp_verify"
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    public function isActive()
    {
        return $this->is_active;
    }

    public function holiday() {
        return $this->hasMany('App\Holiday');
    }

    public function order() {
        return $this->hasMany('App\Order');
    }

    public function customer() {
        return $this->hasOne('App\Customer', 'user_id', 'id');
    }
}
