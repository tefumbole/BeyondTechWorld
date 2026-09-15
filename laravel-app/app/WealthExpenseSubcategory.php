<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthExpenseSubcategory extends Model
{
    protected $table = 'wealth_expense_subcategories';

    protected $fillable = ['bucket_id', 'code', 'name', 'is_active'];

    public function bucket()
    {
        return $this->belongsTo(WealthAllocationBucket::class, 'bucket_id');
    }
}
