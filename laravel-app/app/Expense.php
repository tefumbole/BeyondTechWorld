<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable =[
        "reference_no", "expense_category_id", "category_id", "warehouse_id", "account_id", "user_id", "cash_register_id", "amount", "note",
        "allocation_bucket_id", "wealth_subcategory_id", "program_id", "biller_id", "employee_id", "entity_type",
        "payee", "payment_method", "document", "title", "created_by", "updated_by"
    ];

    public function warehouse()
    {
    	return $this->belongsTo('App\Warehouse');
    }

    public function expenseCategory() {
    	return $this->belongsTo('App\ExpenseCategory');
    }

    public function category() {
        return $this->belongsTo('App\Category');
    }

    public function allocationBucket()
    {
        return $this->belongsTo(WealthAllocationBucket::class, 'allocation_bucket_id');
    }

    public function wealthSubcategory()
    {
        return $this->belongsTo(WealthExpenseSubcategory::class, 'wealth_subcategory_id');
    }

    public function program()
    {
        return $this->belongsTo(WealthProgram::class, 'program_id');
    }
}
