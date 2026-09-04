<?php

namespace App;

use App\Traits\NormalizesWhatsAppPhones;
use Illuminate\Database\Eloquent\Model;

class FuneralEulogy extends Model
{
    use NormalizesWhatsAppPhones;

    protected $whatsappPhoneAttributes = ['phone'];

    protected $table = 'funeral_eulogies';

    protected $fillable = [
        'campaign_id', 'customer_id', 'name', 'phone', 'body', 'signature_path', 'selfie_path',
    ];

    public function campaign()
    {
        return $this->belongsTo(FuneralCampaign::class, 'campaign_id');
    }

    public function excerpt($len = 220)
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $this->body));
        if (strlen($text) <= $len) {
            return $text;
        }

        return rtrim(substr($text, 0, $len)).'…';
    }
}
