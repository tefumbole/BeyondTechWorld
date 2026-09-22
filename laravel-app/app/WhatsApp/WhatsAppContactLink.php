<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppContactLink extends Model
{
    protected $table = 'whatsapp_contact_links';

    protected $fillable = [
        'contact_id', 'linkable_type', 'linkable_id', 'role',
    ];

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function linkable()
    {
        return $this->morphTo();
    }
}
