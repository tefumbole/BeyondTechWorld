<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Generic Contracts Module instance (table: contracts).
 * Named BtwContract to avoid clash with BookingContract / EventContract.
 */
class BtwContract extends Model
{
    use SoftDeletes;

    protected $table = 'contracts';
    public $incrementing = false;
    protected $keyType = 'string';

    const STATUS_DRAFT = 'draft';
    const STATUS_IN_REVIEW = 'in_review';
    const STATUS_READY_TO_SEND = 'ready_to_send';
    const STATUS_AWAITING_CLIENT = 'awaiting_client_signature';
    const STATUS_AWAITING_ADMIN = 'awaiting_admin_signature';
    const STATUS_SIGNED = 'signed';
    const STATUS_DECLINED = 'declined';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'id', 'number', 'type_id', 'template_id', 'template_version_id', 'title', 'status', 'owner_id',
        'current_revision_id', 'effective_date', 'start_date', 'end_date', 'value', 'currency',
        'jurisdiction', 'purpose', 'payment_schedule', 'primary_link_type', 'primary_link_id',
        'signature_expires_at', 'sent_at', 'signed_at', 'cancelled_at', 'superseded_by', 'supersedes',
    ];

    protected $dates = [
        'effective_date', 'start_date', 'end_date', 'signature_expires_at', 'sent_at', 'signed_at', 'cancelled_at',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function type()
    {
        return $this->belongsTo(ContractType::class, 'type_id');
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function templateVersion()
    {
        return $this->belongsTo(ContractTemplateVersion::class, 'template_version_id');
    }

    public function currentRevision()
    {
        return $this->belongsTo(ContractRevision::class, 'current_revision_id');
    }

    public function revisions()
    {
        return $this->hasMany(ContractRevision::class, 'contract_id')->orderByDesc('revision_no');
    }

    public function parties()
    {
        return $this->hasMany(ContractParty::class, 'contract_id');
    }

    public function partyA()
    {
        return $this->hasOne(ContractParty::class, 'contract_id')->where('side', 'A');
    }

    public function partyB()
    {
        return $this->hasOne(ContractParty::class, 'contract_id')->where('side', 'B');
    }

    public function signatories()
    {
        return $this->hasMany(ContractSignatory::class, 'contract_id');
    }

    public function witnesses()
    {
        return $this->hasMany(ContractWitness::class, 'contract_id');
    }

    public function links()
    {
        return $this->hasMany(ContractLink::class, 'contract_id');
    }

    public function values()
    {
        return $this->hasMany(ContractValue::class, 'contract_id');
    }

    public function attachments()
    {
        return $this->hasMany(ContractAttachment::class, 'contract_id');
    }

    public function documents()
    {
        return $this->hasMany(ContractDocument::class, 'contract_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(ContractAuditLog::class, 'contract_id')->orderByDesc('created_at');
    }

    public function reminders()
    {
        return $this->hasMany(ContractReminder::class, 'contract_id')->orderBy('reminder_time');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function statusLabel()
    {
        return ucwords(str_replace('_', ' ', (string) $this->status));
    }

    public function signatureProgress()
    {
        $required = $this->signatories()->where('required', true)->count();
        $signed = $this->signatories()->where('required', true)->where('status', 'signed')->count();

        return $signed.' of '.$required;
    }

    public function isEditable()
    {
        // Drafts/reviews edit in place; post-send statuses still open edit (new revision).
        return ! in_array($this->status, [
            self::STATUS_SIGNED,
            self::STATUS_CANCELLED,
            self::STATUS_SUPERSEDED,
        ], true);
    }

    public function editsInPlace()
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_REVIEW, self::STATUS_READY_TO_SEND], true);
    }

    public function isSigned()
    {
        return $this->status === self::STATUS_SIGNED;
    }
}
