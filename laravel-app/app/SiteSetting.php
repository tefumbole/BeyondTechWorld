<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $table = 'site_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    /**
     * Get a stored value. JSON values are decoded to arrays. Resilient to a
     * missing table (returns the default) so public pages never break.
     */
    public static function getValue($key, $default = null)
    {
        try {
            $row = static::find($key);
        } catch (\Throwable $e) {
            return $default;
        }

        if (! $row) {
            return $default;
        }

        $decoded = json_decode($row->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public static function setValue($key, $value)
    {
        $stored = is_array($value) ? json_encode(array_values($value)) : $value;
        static::updateOrCreate(['key' => $key], ['value' => $stored]);
    }

    public static function setJson($key, array $value)
    {
        static::updateOrCreate(['key' => $key], ['value' => json_encode($value)]);
    }
}
