<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable =[

        "purchase_id", "user_id", "sale_id", "cash_register_id", "account_id", "payment_reference", "amount", "used_points", "change", "paying_method", "payment_note", "debit_booking_id"
    ];

    public function accounts() {
        return $this->belongsTo('App\Account', 'account_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($payment) {
            if (! $payment->sale_id) {
                return;
            }
            try {
                $sale = Sale::find($payment->sale_id);
                if ($sale) {
                    app(\App\Services\Wealth\WealthIncomeSync::class)->syncSale($sale);
                }
            } catch (\Throwable $e) {
            }
        });
    }
}
