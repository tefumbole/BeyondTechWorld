<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BookingProduct extends Model
{

    protected $fillable =[
        "booking_id", "product_id", "category_id", "warehouse_id", "product_batch_id", "multi_product_batch_id", "multi_product_batch_qty", "variant_id", "qty", "sale_unit_id", "net_unit_price", "discount", "tax_rate", "tax", "total", "start", "end", "is_return", "is_notified", "return_reminder_sent_at", "late_notice_sent_at", "booking_method", "number_duration"
    ];

    public function product()
    {
        return $this->belongsTo('App\Product', 'product_id');
    }

    public function booking()
    {
        return $this->belongsTo('App\Booking', 'booking_id');
    }
}
