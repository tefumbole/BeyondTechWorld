<?php

namespace App\Services\WhatsApp;

use App\User;
use App\WhatsApp\WhatsAppOwnerUser;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class OwnerAuthorizationService
{
    public function authorizePhone($phone)
    {
        if (! Schema::hasTable('whatsapp_owner_users') || $phone === null || $phone === '') {
            return null;
        }
        $identity = app(WhatsAppIdentityService::class);
        $normalized = $identity->normalize($phone);
        $matches = $identity->resolve($phone);
        $userId = null;
        foreach ($matches as $match) {
            if (isset($match['role']) && $match['role'] === 'user' && ! empty($match['id'])) {
                $userId = (int) $match['id'];
                break;
            }
            if (isset($match['type']) && $match['type'] === 'user' && ! empty($match['id'])) {
                $userId = (int) $match['id'];
                break;
            }
        }
        if (! $userId) {
            $user = User::where('phone', $normalized)->orWhere('phone', $phone)->first();
            $userId = $user ? (int) $user->id : 0;
        }
        if (! $userId) {
            return null;
        }
        $row = WhatsAppOwnerUser::where('user_id', $userId)->where('enabled', true)->first();
        if (! $row) {
            return null;
        }
        if ($row->normalized_phone !== '' && $row->normalized_phone !== $normalized && $row->normalized_phone !== $phone) {
            return null;
        }
        $user = User::find($userId);
        if (! $user || ! $this->hasOwnerPermission($user)) {
            return null;
        }

        return $user;
    }

    public function hasOwnerPermission(User $user)
    {
        $role = Role::find($user->role_id);
        if (! $role) {
            return false;
        }
        try {
            return $role->hasPermissionTo('whatsapp.owner') || $role->hasPermissionTo('whatsapp.ai.manage');
        } catch (\Exception $e) {
            return (int) $user->role_id === 1;
        }
    }
}
