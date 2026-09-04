<?php

namespace App;

use App\Traits\NormalizesWhatsAppPhones;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles;
    use NormalizesWhatsAppPhones;

    protected $whatsappPhoneAttributes = ['phone', 'additional_phone'];

    protected $fillable = [
        'name', 'username', 'email', 'password', 'phone', 'additional_phone', 'company_name', 'role_id', 'biller_id', 'warehouse_id', 'is_active', 'is_deleted', 'sign', 'stemp', 'approve', 'sign_request_token', 'sign_request_type', 'sign_request_expires_at', 'otp', 'otp_time', 'otp_verify', 'must_set_password',
    ];

    protected $casts = [
        'must_set_password' => 'boolean',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $dates = [
        'sign_request_expires_at',
        'otp_time',
    ];

    public function isActive()
    {
        return $this->is_active;
    }

    /** First usable WhatsApp number on the staff profile. */
    public function whatsappPhone()
    {
        foreach (['phone', 'additional_phone'] as $attr) {
            $value = trim((string) $this->{$attr});
            if ($value !== '') {
                return $value;
            }
        }

        return null;
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
