<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuotationQuote extends Model
{
    const MODE_OVERALL = 'overall';
    const MODE_LINES = 'lines';

    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';

    protected $table = 'quotation_quotes';

    protected $fillable = [
        'quotation_id',
        'mode',
        'proposed_grand_total',
        'original_grand_total',
        'client_note',
        'status',
        'admin_note',
        'admin_user_id',
    ];

    protected $casts = [
        'proposed_grand_total' => 'float',
        'original_grand_total' => 'float',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function lines()
    {
        return $this->hasMany(QuotationQuoteLine::class, 'quotation_quote_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isOverall()
    {
        return $this->mode === self::MODE_OVERALL;
    }

    public function isLines()
    {
        return $this->mode === self::MODE_LINES;
    }
}
