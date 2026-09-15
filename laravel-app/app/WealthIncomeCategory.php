<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WealthIncomeCategory extends Model
{
    protected $table = 'wealth_income_categories';

    protected $fillable = ['code', 'name', 'is_active'];
}
