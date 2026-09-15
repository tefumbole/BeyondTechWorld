<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthAllocationRule extends Model
{
    protected $table = 'wealth_allocation_rules';

    protected $fillable = ['biller_id', 'basis', 'is_active'];

    public function lines()
    {
        return $this->hasMany(WealthAllocationRuleLine::class, 'rule_id');
    }

    public function biller()
    {
        return $this->belongsTo(Biller::class);
    }
}
