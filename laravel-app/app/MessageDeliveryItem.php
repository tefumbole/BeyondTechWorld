<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MessageDeliveryItem extends Model
{
    protected $table = 'message_delivery_items';

    protected $guarded = [];

    protected $dates = ['sent_at'];

    public function batch()
    {
        return $this->belongsTo(MessageDeliveryBatch::class, 'batch_id');
    }
}
