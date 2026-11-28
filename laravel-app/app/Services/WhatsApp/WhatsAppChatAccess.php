<?php

namespace App\Services\WhatsApp;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * People who scan a WhatsApp code get this role and nothing else.
 * Platform administrators keep the role they already have.
 */
class WhatsAppChatAccess
{
    const ROLE = 'WhatsApp Chats';

    public function grant(User $user)
    {
        if (in_array((int) $user->role_id, [1, 2], true)) {
            return $user;
        }
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return $user;
        }
        if (! Schema::hasColumn('users', 'role_id')) {
            return $user;
        }
        $roleId = $this->roleId();
        if ((int) $user->role_id !== $roleId) {
            $user->role_id = $roleId;
            $user->save();
        }

        return $user->fresh() ?: $user;
    }

    public function holds($user)
    {
        if (! $user || ! Schema::hasTable('roles')) {
            return false;
        }
        $role = DB::table('roles')->where('id', (int) $user->role_id)->first();

        return $role && (string) $role->name === self::ROLE;
    }

    public function sessionLimited()
    {
        return (bool) session('whatsapp_chats_only');
    }

    public function rememberSession()
    {
        session(['whatsapp_chats_only' => 1]);
    }

    protected function roleId()
    {
        $existing = DB::table('roles')->where('name', self::ROLE)->where('guard_name', 'web')->first();
        if ($existing) {
            $this->sync((int) $existing->id);

            return (int) $existing->id;
        }
        $row = [
            'name' => self::ROLE,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('roles', 'is_active')) {
            $row['is_active'] = 1;
        }
        if (Schema::hasColumn('roles', 'description')) {
            $row['description'] = 'WhatsApp chats only';
        }
        $id = (int) DB::table('roles')->insertGetId($row);
        $this->sync($id);

        return $id;
    }

    protected function sync($roleId)
    {
        $names = ['whatsapp.view', 'whatsapp.conversations', 'whatsapp.reply'];
        $ids = [];
        foreach ($names as $name) {
            $perm = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
            if (! $perm) {
                $ids[] = (int) DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $ids[] = (int) $perm->id;
            }
        }
        DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        foreach ($ids as $id) {
            DB::table('role_has_permissions')->insert([
                'permission_id' => $id,
                'role_id' => $roleId,
            ]);
        }
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }
}
