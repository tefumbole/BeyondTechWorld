<?php

namespace App\Antivirus;

use App\Services\Cloud\BelongsToCloudTenant;
use App\Support\AntivirusCatalog;
use Illuminate\Database\Eloquent\Model;

class AntivirusRequest extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'antivirus_requests';

    protected $guarded = ['cloud_tenant_id'];

    protected $dates = [
        'signed_at',
        'phone_verified_at',
        'submitted_at',
        'station_approved_at',
        'forwarded_at',
        'finance_acknowledged_at',
        'chief_phone_verified_at',
        'chief_signed_at',
        'approved_at',
        'declined_at',
        'payment_date',
        'payment_recorded_at',
        'receipt_phone_verified_at',
        'receipt_confirmed_at',
        'pdf_expires_at',
        'receipt_expires_at',
    ];

    public function approvers()
    {
        return $this->hasMany(AntivirusApprover::class, 'antivirus_request_id')->orderBy('position');
    }

    public function activities()
    {
        return $this->hasMany(AntivirusActivity::class, 'antivirus_request_id')->orderBy('id');
    }

    public function decisionLabel()
    {
        $map = [
            'draft' => 'Draft',
            'awaiting_station' => 'Awaiting Station Approval',
            'awaiting_chief' => 'Awaiting Chief Approval',
            'approved' => 'Approved',
            'declined' => 'Declined',
        ];

        return isset($map[$this->status]) ? $map[$this->status] : (string) $this->status;
    }

    public function paymentLabel()
    {
        return $this->payment_status === 'recorded' ? 'Recorded' : 'Not recorded';
    }

    public function receiptLabel()
    {
        if ($this->receipt_status === 'confirmed') {
            return 'Confirmed';
        }
        if ($this->receipt_status === 'shortfall') {
            return 'Shortfall';
        }
        if ($this->receipt_status === 'awaiting') {
            return 'Awaiting confirmation';
        }

        return 'Not requested';
    }

    public function progressLabel()
    {
        if ($this->status === 'declined') {
            return 'Declined';
        }
        if ($this->status === 'approved') {
            return 'Approved';
        }
        if ($this->status === 'awaiting_chief') {
            return 'With the Chief';
        }
        $total = $this->approvers ? $this->approvers->count() : 0;
        $signed = $this->approvers ? $this->approvers->where('status', 'signed')->count() : 0;

        return $signed.' of '.$total.' station signatures';
    }

    public function requesterRoleLabel()
    {
        return AntivirusCatalog::roleLabel('requester', $this->requester_role, $this->requester_role_other);
    }

    public function signatureIsCurrent()
    {
        return $this->signature_path
            && $this->sign_fingerprint
            && hash_equals($this->sign_fingerprint, AntivirusCatalog::fingerprint($this));
    }
}
