<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MessageDeliveryBatch extends Model
{
    protected $table = 'message_delivery_batches';

    protected $guarded = [];

    protected $dates = ['started_at', 'finished_at'];

    public function items()
    {
        return $this->hasMany(MessageDeliveryItem::class, 'batch_id');
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class, 'letter_id');
    }

    public function queuedBy()
    {
        return $this->belongsTo(User::class, 'queued_by');
    }

    public function progressPercent(): int
    {
        $total = (int) $this->total;
        if ($total <= 0) {
            return 0;
        }
        $done = (int) $this->sent_count + (int) $this->failed_count;

        return (int) min(100, round(($done / $total) * 100));
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'sending'], true);
    }
}
