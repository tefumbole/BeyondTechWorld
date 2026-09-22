<?php

namespace App\WhatsApp;

use App\Customer;
use App\User;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $table = 'leads';

    protected $fillable = [
        'contact_id', 'conversation_id', 'name', 'normalized_phone', 'company',
        'source', 'category', 'summary', 'status', 'priority', 'assigned_user_id',
        'first_enquiry', 'latest_enquiry', 'follow_up_at', 'converted_customer_id',
        'converted_at', 'lost_reason', 'last_activity_at',
    ];

    protected $dates = ['follow_up_at', 'converted_at', 'last_activity_at'];

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    public function conversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class, 'lead_id')->orderBy('id');
    }

    public function notes()
    {
        return $this->hasMany(WhatsAppNote::class, 'lead_id')->orderBy('id');
    }

    public function isClosed()
    {
        return in_array($this->status, LeadCatalog::closedStatuses(), true);
    }

    public function categoryLabel()
    {
        $map = LeadCatalog::categories();

        return isset($map[$this->category]) ? $map[$this->category] : ($this->category ?: '—');
    }

    public function statusLabel()
    {
        $map = LeadCatalog::statuses();

        return isset($map[$this->status]) ? $map[$this->status] : $this->status;
    }
}
