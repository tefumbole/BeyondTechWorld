<?php

namespace App\Cloud;

use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A platform user belonging to one CloudTenant.
 * One users row can have many memberships. Ending a membership does not delete the user.
 * PLATFORM SUPER ADMIN is not a membership role. It stays on the existing ERP RBAC until a later phase.
 */
class CloudTenantMembership extends Model
{
    protected $table = 'cloud_tenant_memberships';

    protected $fillable = [
        'cloud_tenant_id',
        'user_id',
        'role_id',
        'membership_role',
        'is_owner',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'is_owner' => 'boolean',
        'joined_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function (CloudTenantMembership $membership) {
            if (! $membership->membership_role) {
                $membership->membership_role = CloudMembershipRole::STAFF;
            }
            if (! $membership->status) {
                $membership->status = CloudMembershipStatus::INVITED;
            }
            if ($membership->is_owner === null) {
                $membership->is_owner = $membership->membership_role === CloudMembershipRole::OWNER;
            }
        });
    }

    public function cloudTenant()
    {
        return $this->belongsTo(CloudTenant::class, 'cloud_tenant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * End access to this company. The users account remains.
     */
    public function remove()
    {
        $this->status = CloudMembershipStatus::REMOVED;
        $this->is_owner = false;
        $this->save();

        return $this;
    }
}
