<?php

namespace App\WhatsApp;

use App\Quotation;
use Illuminate\Database\Eloquent\Model;

class RentalRequest extends Model
{
    protected $table = 'whatsapp_rental_requests';

    const COLLECTING = 'COLLECTING_REQUIREMENTS';
    const READY = 'READY_FOR_AVAILABILITY';
    const CHECKED = 'AVAILABILITY_CHECKED';
    const PROPOSAL = 'PROPOSAL_READY';
    const AWAITING_CUSTOMER = 'AWAITING_CUSTOMER_CONFIRMATION';
    const AWAITING_STAFF = 'AWAITING_STAFF_APPROVAL';
    const QUOTE_CREATED = 'QUOTATION_CREATED';
    const QUOTE_SENT = 'QUOTATION_SENT';
    const REVISION = 'REVISION_REQUESTED';
    const ACCEPTED = 'ACCEPTED';
    const CANCELLED = 'CANCELLED';
    const HANDED_OVER = 'HANDED_OVER';

    protected $fillable = [
        'conversation_id', 'contact_id', 'lead_id', 'customer_id', 'event_type', 'event_date',
        'event_date_text', 'setup_at', 'event_start_at', 'event_end_at', 'return_at', 'location',
        'venue', 'indoor_outdoor', 'attendance', 'categories_json', 'lines_json', 'requirements',
        'budget', 'delivery_required', 'setup_required', 'technicians_required', 'status',
        'quotation_id', 'availability_checked_at', 'availability_note', 'proposal_total', 'created_by',
    ];

    protected $dates = ['event_date', 'setup_at', 'event_start_at', 'event_end_at', 'return_at', 'availability_checked_at'];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function activities()
    {
        return $this->hasMany(RentalActivity::class, 'rental_request_id');
    }

    public function lines()
    {
        $decoded = json_decode((string) $this->lines_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setLines(array $lines)
    {
        $this->lines_json = json_encode(array_values($lines));
    }

    public function categories()
    {
        $decoded = json_decode((string) $this->categories_json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
