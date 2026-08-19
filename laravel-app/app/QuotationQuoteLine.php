<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QuotationQuoteLine extends Model
{
    protected $table = 'quotation_quote_lines';

    protected $fillable = [
        'quotation_quote_id',
        'product_quotation_id',
        'original_net_unit_price',
        'original_total',
        'proposed_net_unit_price',
        'proposed_total',
    ];

    protected $casts = [
        'original_net_unit_price' => 'float',
        'original_total' => 'float',
        'proposed_net_unit_price' => 'float',
        'proposed_total' => 'float',
    ];

    public function quote()
    {
        return $this->belongsTo(QuotationQuote::class, 'quotation_quote_id');
    }

    public function productQuotation()
    {
        return $this->belongsTo(ProductQuotation::class, 'product_quotation_id');
    }
}
