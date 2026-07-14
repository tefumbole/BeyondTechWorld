<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventLabourBudget extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id', 'total_budget', 'allocated_amount', 'distribution_mode', 'notes',
    ];

    protected $casts = [
        'total_budget' => 'integer',
        'allocated_amount' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function remaining()
    {
        return max(0, $this->total_budget - $this->allocated_amount);
    }

    public function variance()
    {
        return $this->allocated_amount - $this->total_budget;
    }
}
