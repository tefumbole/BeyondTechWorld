<?php

namespace App\WhatsApp;

use App\User;
use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    const ENQUIRY = 'enquiry_received';
    const ASSIGNED = 'staff_assigned';
    const STATUS = 'status_changed';
    const NOTE = 'note_added';
    const QUOTATION_REQUESTED = 'quotation_requested';
    const QUOTATION_LINKED = 'quotation_linked';
    const FOLLOW_UP = 'follow_up_set';
    const CUSTOMER_CREATED = 'customer_created';
    const CUSTOMER_LINKED = 'customer_linked';
    const CONVERTED = 'converted';
    const LOST = 'lost';
    const CLASSIFIED = 'classified';

    protected $table = 'lead_activities';

    protected $fillable = [
        'lead_id', 'type', 'body', 'actor_user_id', 'message_id', 'meta',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function metaArray()
    {
        if (! $this->meta) {
            return [];
        }
        $decoded = json_decode($this->meta, true);

        return is_array($decoded) ? $decoded : [];
    }
}
