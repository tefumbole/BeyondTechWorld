<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthProgram extends Model
{
    protected $table = 'wealth_programs';

    protected $fillable = [
        'name', 'code', 'biller_id', 'manager_user_id', 'description', 'start_date', 'end_date',
        'proposed_budget', 'expected_income', 'currency', 'status', 'cover_image', 'notes',
        'created_by', 'updated_by',
    ];

    public function biller()
    {
        return $this->belongsTo(Biller::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function budgetLines()
    {
        return $this->hasMany(WealthProgramBudgetLine::class, 'program_id');
    }
}
