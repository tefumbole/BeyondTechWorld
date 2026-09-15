<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthIncome extends Model
{
    protected $table = 'wealth_income';

    protected $fillable = [
        'source_type', 'source_id', 'occurred_at', 'amount', 'currency', 'title', 'description',
        'biller_id', 'user_id', 'employee_id', 'customer_id', 'program_id', 'income_category_id',
        'payment_method', 'reference', 'attachment', 'notes', 'status', 'created_by', 'updated_by',
    ];

    protected $dates = ['occurred_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function biller()
    {
        return $this->belongsTo(Biller::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function program()
    {
        return $this->belongsTo(WealthProgram::class, 'program_id');
    }

    public function category()
    {
        return $this->belongsTo(WealthIncomeCategory::class, 'income_category_id');
    }

    public function isAutomatic()
    {
        return in_array($this->source_type, ['sale', 'pos', 'payment'], true);
    }

    public function sourceUrl()
    {
        if (in_array($this->source_type, ['sale', 'pos'], true) && $this->source_id) {
            return url('sales/'.$this->source_id);
        }

        return null;
    }
}
