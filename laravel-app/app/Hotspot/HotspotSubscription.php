<?php

namespace App\Hotspot;

use App\Customer;
use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class HotspotSubscription extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_subscriptions';

    protected $fillable = [
        'customer_id', 'location_id', 'package_id', 'router_id', 'username', 'state',
        'starts_at', 'expires_at', 'data_cap_bytes', 'data_used_bytes', 'connected_seconds',
    ];

    protected $hidden = ['password_hash'];

    protected $dates = [
        'starts_at', 'expires_at', 'receipt_sent_at', 'expiry_sent_at', 'cap_sent_at', 'reminder_sent_at',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function package()
    {
        return $this->belongsTo(HotspotPackage::class, 'package_id');
    }

    public function location()
    {
        return $this->belongsTo(HotspotLocation::class, 'location_id');
    }

    public function cloudTenantRelations()
    {
        return [
            'customer_id' => Customer::class,
            'location_id' => HotspotLocation::class,
            'package_id' => HotspotPackage::class,
            'router_id' => HotspotRouter::class,
        ];
    }

    public function setAccessSecretAttribute($value)
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['password_hash'] = Crypt::encryptString($value);
    }

    public function getAccessSecretAttribute()
    {
        return HotspotRouter::decrypt(isset($this->attributes['password_hash']) ? $this->attributes['password_hash'] : null);
    }
}
