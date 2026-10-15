<?php

namespace App\Hotspot;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class HotspotRouter extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'hotspot_routers';

    protected $fillable = [
        'location_id', 'name', 'identity', 'api_host', 'api_port', 'api_user', 'ros_version', 'status',
    ];

    protected $hidden = ['api_password', 'radius_secret'];

    public function location()
    {
        return $this->belongsTo(HotspotLocation::class, 'location_id');
    }

    public function cloudTenantRelations()
    {
        return ['location_id' => HotspotLocation::class];
    }

    public function setApiPasswordAttribute($value)
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['api_password'] = Crypt::encryptString($value);
    }

    public function getApiPasswordAttribute()
    {
        return self::decrypt(isset($this->attributes['api_password']) ? $this->attributes['api_password'] : null);
    }

    public function setRadiusSecretAttribute($value)
    {
        if ($value === null || $value === '') {
            return;
        }
        $this->attributes['radius_secret'] = Crypt::encryptString($value);
    }

    public function getRadiusSecretAttribute()
    {
        return self::decrypt(isset($this->attributes['radius_secret']) ? $this->attributes['radius_secret'] : null);
    }

    public static function decrypt($value)
    {
        if (! $value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
