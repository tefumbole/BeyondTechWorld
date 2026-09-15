<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthAllocation extends Model
{
    protected $table = 'wealth_allocations';

    protected $fillable = [
        'period_key', 'entity_type', 'entity_id', 'bucket_id',
        'expected_amount', 'used_amount', 'remaining_amount',
    ];

    public function bucket()
    {
        return $this->belongsTo(WealthAllocationBucket::class, 'bucket_id');
    }
}
