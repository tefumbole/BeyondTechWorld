<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthAllocationBucket extends Model
{
    protected $table = 'wealth_allocation_buckets';

    protected $fillable = ['code', 'name', 'label', 'sort_order', 'is_active'];

    public function subcategories()
    {
        return $this->hasMany(WealthExpenseSubcategory::class, 'bucket_id');
    }
}
