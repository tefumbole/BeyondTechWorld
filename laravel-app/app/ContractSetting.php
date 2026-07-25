<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ContractSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue($key, $default = null)
    {
        $row = static::where('key', $key)->first();
        if (! $row) {
            return $default;
        }
        $decoded = json_decode($row->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public static function setValue($key, $value)
    {
        $stored = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;

        return static::updateOrCreate(['key' => $key], ['value' => $stored]);
    }
}
