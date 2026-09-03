<?php

namespace App;

use App\Traits\NormalizesWhatsAppPhones;
use Illuminate\Database\Eloquent\Model;

class FuneralPledge extends Model
{
    use NormalizesWhatsAppPhones;

    protected $whatsappPhoneAttributes = ['phone'];

    protected $table = 'funeral_pledges';

    const KIND_PLEDGE = 'pledge';
    const KIND_PAYMENT = 'payment';

    const STATUS_PLEDGED = 'pledged';
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'campaign_id', 'item_id', 'customer_id', 'name', 'phone',
        'amount', 'kind', 'status', 'campay_reference', 'stripe_session_id', 'paid_at',
    ];

    protected $dates = ['paid_at'];

    public function campaign()
    {
        return $this->belongsTo(FuneralCampaign::class, 'campaign_id');
    }

    public function item()
    {
        return $this->belongsTo(FuneralItem::class, 'item_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function countsTowardRaised()
    {
        return in_array($this->status, [self::STATUS_PLEDGED, self::STATUS_PAID], true);
    }
}
