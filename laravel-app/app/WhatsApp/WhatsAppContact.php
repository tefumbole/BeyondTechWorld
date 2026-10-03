<?php

namespace App\WhatsApp;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppContact extends Model
{
    use BelongsToCloudTenant;
    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'normalized_phone', 'display_phone', 'wa_name', 'blocked_at',
        'call_name', 'relationship', 'preferred_language', 'voice_note',
    ];

    protected $dates = ['blocked_at'];

    public function links()
    {
        return $this->hasMany(WhatsAppContactLink::class, 'contact_id');
    }

    public function conversations()
    {
        return $this->hasMany(WhatsAppConversation::class, 'contact_id');
    }

    public function isBlocked()
    {
        return $this->blocked_at !== null;
    }

    public function displayName()
    {
        if (trim((string) $this->wa_name) !== '') {
            return $this->wa_name;
        }

        return $this->display_phone ?: $this->normalized_phone;
    }
}
