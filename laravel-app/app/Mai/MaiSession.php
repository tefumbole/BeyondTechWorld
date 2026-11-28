<?php

namespace App\Mai;

use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudTenantMembership;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MaiSession extends Model
{
    protected $table = 'mai_sessions';

    protected $fillable = [
        'public_id', 'user_id', 'cloud_tenant_id', 'provider', 'worker_session_id',
        'phone_number', 'expected_phone', 'display_name', 'status', 'attempt_id',
        'pairing_code', 'pairing_expires_at', 'connected_at', 'unlinked_at',
        'ai_enabled', 'last_error',
    ];

    protected $hidden = ['worker_session_id'];

    protected $dates = ['pairing_expires_at', 'connected_at', 'unlinked_at'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function allows(User $user)
    {
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }
        if (! $this->cloud_tenant_id || ! Schema::hasTable('cloud_tenant_memberships')) {
            return false;
        }

        return CloudTenantMembership::where('cloud_tenant_id', $this->cloud_tenant_id)
            ->where('user_id', $user->id)
            ->where('status', CloudMembershipStatus::ACTIVE)
            ->exists();
    }

    public function canManage(User $user)
    {
        if ((int) $this->user_id === (int) $user->id) {
            return true;
        }
        if (! $this->cloud_tenant_id || ! Schema::hasTable('cloud_tenant_memberships')) {
            return false;
        }
        $membership = CloudTenantMembership::where('cloud_tenant_id', $this->cloud_tenant_id)
            ->where('user_id', $user->id)
            ->where('status', CloudMembershipStatus::ACTIVE)
            ->first();
        if (! $membership) {
            return false;
        }

        return (bool) $membership->is_owner || in_array((string) $membership->membership_role, [
            CloudMembershipRole::OWNER,
            CloudMembershipRole::ADMIN,
        ], true);
    }

    public function pairingOpen()
    {
        if (! $this->pairing_expires_at) {
            return false;
        }

        return $this->pairing_expires_at->isFuture();
    }
}
