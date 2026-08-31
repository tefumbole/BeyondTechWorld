<?php

namespace App;

use App\Traits\NormalizesWhatsAppPhones;
use Illuminate\Database\Eloquent\Model;

class StaffPermission extends Model
{
    use NormalizesWhatsAppPhones;

    protected $whatsappPhoneAttributes = ['phone'];

    protected $table = 'staff_permissions';
    protected $keyType = 'string';
    public $incrementing = false;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'id', 'user_id', 'full_name', 'email', 'phone', 'company_role',
        'from_at', 'to_at', 'reason', 'subject', 'status', 'admin_note', 'instructions',
        'letter_footer', 'reviewed_by', 'reviewed_at', 'reference_number', 'letter_id',
    ];

    protected $dates = ['from_at', 'to_at', 'reviewed_at'];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel()
    {
        return ucfirst($this->status);
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class, 'letter_id');
    }

    /**
     * @return array<string,int>
     */
    public static function tabCounts()
    {
        return [
            'permissions.requests' => (int) static::where('status', self::STATUS_PENDING)->count(),
            'permissions.approved' => (int) static::where('status', self::STATUS_APPROVED)->count(),
            'permissions.denied' => (int) static::where('status', self::STATUS_REJECTED)->count(),
            'permissions.index' => (int) static::count(),
        ];
    }
}
