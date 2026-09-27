<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventPricingRule extends Model
{
    protected $fillable = [
        'key', 'label', 'group', 'amount', 'currency', 'unit', 'active', 'metadata',
    ];

    protected $casts = [
        'amount' => 'float',
        'active' => 'boolean',
    ];

    public static function amountFor($key, $default = 0)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('event_pricing_rules')) {
            return (float) $default;
        }
        $row = static::where('key', $key)->where('active', true)->first();

        return $row ? (float) $row->amount : (float) $default;
    }
}
