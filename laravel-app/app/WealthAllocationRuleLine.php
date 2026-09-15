<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthAllocationRuleLine extends Model
{
    protected $table = 'wealth_allocation_rule_lines';

    protected $fillable = ['rule_id', 'bucket_id', 'percent'];

    public function rule()
    {
        return $this->belongsTo(WealthAllocationRule::class, 'rule_id');
    }

    public function bucket()
    {
        return $this->belongsTo(WealthAllocationBucket::class, 'bucket_id');
    }
}
