<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthInvestment extends Model
{
    protected $table = 'wealth_investments';

    protected $fillable = [
        'name', 'type', 'amount_invested', 'invested_on', 'biller_id', 'user_id', 'program_id',
        'expense_id', 'description', 'current_value', 'expected_return', 'status', 'document',
        'notes', 'created_by', 'updated_by',
    ];

    public function program()
    {
        return $this->belongsTo(WealthProgram::class, 'program_id');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
