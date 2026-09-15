<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable =[
        "code", "name", "is_active", "allocation_bucket_id", "wealth_subcategory_id"
    ];

    public function expense() {
    	return $this->hasMany('App\Expense');
    }
}
