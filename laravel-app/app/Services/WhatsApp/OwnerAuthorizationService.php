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
        $incoming = preg_replace('/\D/', '', (string) $normalized);
        $byListedPhone = $this->userForListedPhone($incoming);
        if ($byListedPhone) {
            return $byListedPhone;
        }
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
        $user = User::find($userId);
        if (! $user || ! $this->hasOwnerPermission($user)) {
            return null;
        }
        $incoming = preg_replace('/\D/', '', (string) $normalized);
        $allowed = array_filter([
            preg_replace('/\D/', '', (string) $row->normalized_phone),
            preg_replace('/\D/', '', (string) $user->phone),
            preg_replace('/\D/', '', (string) $user->additional_phone),
        ]);
        if ($allowed !== [] && $incoming !== '' && ! in_array($incoming, $allowed, true)) {
            return null;
        }

        return $user;
    }

    /**
     * The command phone can differ from the business line. +250794006160 may
     * turn on WhatsApp AI for +237675321739 when that number is the listed owner phone.
     */
    protected function userForListedPhone($incomingDigits)
    {
        if ($incomingDigits === '') {
            return null;
        }
        foreach (WhatsAppOwnerUser::where('enabled', true)->get() as $row) {
            $listed = preg_replace('/\D/', '', (string) $row->normalized_phone);
            if ($listed === '' || $listed !== $incomingDigits) {
                continue;
            }
            $user = User::find($row->user_id);
            if ($user && $this->hasOwnerPermission($user)) {
                return $user;
            }
        }

        return null;
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
