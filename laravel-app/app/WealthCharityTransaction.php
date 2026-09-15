<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthCharityTransaction extends Model
{
    protected $table = 'wealth_charity_transactions';

    protected $fillable = [
        'beneficiary', 'subcategory_id', 'amount', 'given_on', 'biller_id', 'user_id', 'program_id',
        'expense_id', 'description', 'payment_method', 'document', 'notes', 'created_by', 'updated_by',
    ];

    public function subcategory()
    {
        return $this->belongsTo(WealthExpenseSubcategory::class, 'subcategory_id');
    }

    public function program()
    {
        return $this->belongsTo(WealthProgram::class, 'program_id');
    }
}
