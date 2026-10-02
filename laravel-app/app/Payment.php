<?php

namespace App;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToCloudTenant;
    protected $fillable =[

        "purchase_id", "user_id", "sale_id", "cash_register_id", "account_id", "payment_reference", "amount", "used_points", "change", "paying_method", "payment_note", "debit_booking_id"
    ];

    public function accounts() {
        return $this->belongsTo('App\Account', 'account_id');
    }

    public function cloudTenantRelations()
    {
        return [
            'sale_id' => Sale::class,
        ];
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
