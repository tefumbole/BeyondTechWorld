<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthProgramBudgetLine extends Model
{
    protected $table = 'wealth_program_budget_lines';

    protected $fillable = ['program_id', 'name', 'budget_amount', 'subcategory_id', 'warn_percent', 'critical_percent'];

    public function program()
    {
        return $this->belongsTo(WealthProgram::class, 'program_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(WealthExpenseSubcategory::class, 'subcategory_id');
    }
}
