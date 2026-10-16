<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixSetting extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_settings';

    protected $fillable = ['key', 'value'];

    public static function getValue($key, $default = null)
    {
        $row = static::where('key', $key)->first();

        return $row ? $row->value : $default;
    }

    public static function putValue($key, $value)
    {
        $row = static::where('key', $key)->first();
        if (! $row) {
            $row = new static();
            $row->key = $key;
        }
        $row->value = $value;
        $row->save();

        return $row;
    }
}
