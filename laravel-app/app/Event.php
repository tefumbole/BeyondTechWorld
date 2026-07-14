<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $table = 'btw_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'reference_no', 'name', 'slug', 'event_type', 'flyer_path',
        'customer_id', 'client_contact_person', 'client_telephone', 'client_email',
        'venue', 'venue_address', 'city', 'timezone',
        'internal_description', 'internal_notes',
        'packing_at', 'loading_at', 'departure_at',
        'setup_start_at', 'setup_end_at', 'rehearsal_at',
        'event_start_at', 'event_end_at',
        'dismantling_start_at', 'dismantling_end_at', 'return_at',
        'expected_workdays', 'timesheet_deadline_at',
        'booking_id', 'rental_link_mode', 'internal_status', 'labour_mode',
        'created_by', 'updated_by',
    ];

    protected $dates = [
        'packing_at', 'loading_at', 'departure_at',
        'setup_start_at', 'setup_end_at', 'rehearsal_at',
        'event_start_at', 'event_end_at',
        'dismantling_start_at', 'dismantling_end_at', 'return_at',
        'timesheet_deadline_at',
    ];

    const STATUSES = [
        'draft' => 'Draft',
        'planning' => 'Planning',
        'awaiting_approval' => 'Awaiting Approval',
        'approved' => 'Approved',
        'contracts_being_prepared' => 'Contracts Being Prepared',
        'contracts_being_sent' => 'Contracts Being Sent',
        'team_confirmation_pending' => 'Team Confirmation Pending',
        'team_confirmed' => 'Team Confirmed',
        'loading' => 'Loading',
        'in_transit' => 'In Transit',
        'installation_in_progress' => 'Installation in Progress',
        'ready_for_event' => 'Ready for Event',
        'event_in_progress' => 'Event in Progress',
        'dismantling' => 'Dismantling',
        'equipment_returning' => 'Equipment Returning',
        'completed' => 'Completed',
        'postponed' => 'Postponed',
        'cancelled' => 'Cancelled',
    ];

    const TYPES = [
        'wedding' => 'Wedding',
        'concert' => 'Concert',
        'conference' => 'Conference',
        'crusade' => 'Crusade',
        'funeral' => 'Funeral',
        'church_event' => 'Church Event',
        'corporate_event' => 'Corporate Event',
        'birthday' => 'Birthday',
        'festival' => 'Festival',
        'training' => 'Training',
        'private_event' => 'Private Event',
        'other' => 'Other',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Event $event) {
            if (! $event->id) {
                $event->id = (string) Str::uuid();
            }
            if (! $event->reference_no) {
                $event->reference_no = self::nextReferenceNo();
            }
            if (! $event->slug) {
                $event->slug = self::uniqueSlug($event->name);
            }
        });
    }

    public static function nextReferenceNo()
    {
        $year = date('Y');
        $prefix = 'EVT/' . $year . '/';
        $last = self::where('reference_no', 'like', $prefix . '%')
            ->orderBy('reference_no', 'desc')
            ->value('reference_no');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public static function uniqueSlug($name, $ignoreId = null)
    {
        $base = Str::slug($name) ?: 'event';
        $slug = $base;
        $n = 1;
        while (self::where('slug', $slug)->when($ignoreId, function ($q) use ($ignoreId) {
            return $q->where('id', '!=', $ignoreId);
        })->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function publication()
    {
        return $this->hasOne(EventPublication::class, 'event_id');
    }

    public function assignments()
    {
        return $this->hasMany(EventAssignment::class, 'event_id');
    }

    public function labourBudget()
    {
        return $this->hasOne(EventLabourBudget::class, 'event_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(EventStatusHistory::class, 'event_id')->orderBy('changed_at', 'desc');
    }

    public function contracts()
    {
        return $this->hasMany(EventContract::class, 'event_id');
    }

    public function reminders()
    {
        return $this->hasMany(EventReminder::class, 'event_id');
    }

    public function timesheets()
    {
        return $this->hasMany(EventTimesheet::class, 'event_id');
    }

    public function payments()
    {
        return $this->hasMany(EventWorkerPayment::class, 'event_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel()
    {
        return self::STATUSES[$this->internal_status] ?? $this->internal_status;
    }

    public function flyerUrl()
    {
        if (! $this->flyer_path) {
            return null;
        }
        if (preg_match('#^(https?:)?//#', $this->flyer_path) || strpos($this->flyer_path, '/') === 0) {
            return $this->flyer_path;
        }

        return url('public/' . ltrim($this->flyer_path, '/'));
    }
}
