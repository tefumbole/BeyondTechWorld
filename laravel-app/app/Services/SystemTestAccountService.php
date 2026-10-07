<?php

namespace App\Services;

use App\Biller;
use App\BeyondUser;
use App\Roles;
use App\User;
use App\Warehouse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * A person who opens the public system test needs a staff login.
 * A customer record for the same phone must not be the account they receive.
 */
class SystemTestAccountService
{
    public function provision($name, $phone)
    {
        $name = trim((string) $name);
        $phone = trim((string) $phone);
        if ($name === '' || $phone === '') {
            throw new \InvalidArgumentException('Name and phone are required.');
        }

        $role = $this->testerRole();
        $plain = 'Be-'.random_int(100000, 999999);
        $username = $this->username($name, $phone, $role->id);
        $email = $this->email($username);

        $user = $this->findTester($phone, $role->id);
        $created = false;
        if (! $user) {
            $user = new User();
            $user->email = $email;
            $user->is_deleted = 0;
            $created = true;
        }

        $warehouseId = optional(Warehouse::where('is_active', true)->first())->id;
        $billerId = optional(Biller::where('is_active', true)->first())->id;

        $user->name = $name;
        $user->username = $username;
        $user->phone = $phone;
        $user->password = bcrypt($plain);
        $user->role_id = $role->id;
        $user->is_active = 1;
        $user->is_deleted = 0;
        if ($warehouseId && ! $user->warehouse_id) {
            $user->warehouse_id = $warehouseId;
        }
        if ($billerId && ! $user->biller_id) {
            $user->biller_id = $billerId;
        }
        if ($created || trim((string) $user->email) === '') {
            $user->email = $email;
        }
        $user->save();

        try {
            $user->syncRoles([$role->name]);
        } catch (\Throwable $e) {
            \Log::warning('System test role sync failed: '.$e->getMessage());
        }

        $this->alignPortalAccount($phone, $username, $plain);

        return [
            'username' => $user->username,
            'password' => $plain,
            'created' => $created,
        ];
    }

    protected function testerRole()
    {
        $role = Role::where('name', 'System Tester')->where('guard_name', 'web')->first();
        if (! $role) {
            $row = Roles::create([
                'name' => 'System Tester',
                'guard_name' => 'web',
                'is_active' => 1,
                'description' => 'Public system test. Opens the admin, not the customer home.',
            ]);
            $role = Role::find($row->id);
        }
        Roles::where('id', $role->id)->update([
            'is_active' => 1,
            'description' => 'Public system test. Opens the admin, not the customer home.',
        ]);

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $names = [];
            foreach ($admin->permissions as $permission) {
                if (! $this->deniedPermission($permission->name)) {
                    $names[] = $permission->name;
                }
            }
            $role->syncPermissions($names);
        }

        return $role;
    }

    protected function deniedPermission($name)
    {
        $name = strtolower((string) $name);
        $bits = [
            'backup',
            'empty_database',
            'empty-database',
            'restore',
            'role-permission',
            'roles-index',
            'roles_index',
            'general_setting',
            'general-setting',
            'mail_setting',
            'sms_setting',
        ];
        foreach ($bits as $bit) {
            if (strpos($name, $bit) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function findTester($phone, $roleId)
    {
        $tail = substr(preg_replace('/\D+/', '', $phone), -9);
        if (strlen($tail) < 8) {
            return null;
        }

        return User::where('is_active', 1)
            ->where('role_id', $roleId)
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhere('is_deleted', false)->orWhereNull('is_deleted');
            })
            ->whereRaw(
                "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                [$tail]
            )
            ->first();
    }

    protected function username($name, $phone, $testerRoleId)
    {
        $skip = ['mr', 'mrs', 'ms', 'miss', 'dr', 'prof'];
        $base = 'tester';
        foreach (preg_split('/\s+/', trim($name)) as $part) {
            $word = preg_replace('/[^a-z]/', '', strtolower($part));
            if (strlen($word) >= 3 && ! in_array($word, $skip, true)) {
                $base = $word;
                break;
            }
        }

        $existing = $this->findTester($phone, $testerRoleId);
        $ownId = $existing ? $existing->id : 0;
        $username = $base;
        $i = 2;
        while (User::whereRaw('LOWER(username) = ?', [strtolower($username)])->where('id', '!=', $ownId)->exists()) {
            $username = $base.$i;
            $i++;
        }

        return $username;
    }

    protected function email($username)
    {
        $email = $username.'@testers.beyondtechworld.com';
        $i = 2;
        while (User::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
            $email = $username.$i.'@testers.beyondtechworld.com';
            $i++;
        }

        return $email;
    }

    /**
     * The portal account for this phone must not stay a customer once a test login exists.
     */
    protected function alignPortalAccount($phone, $username, $plain)
    {
        $portal = BeyondUser::where('phone', $phone)->first();
        if (! $portal) {
            $portal = BeyondUser::whereRaw('LOWER(username) = ?', [strtolower($username)])->first();
        }
        if (! $portal) {
            return;
        }
        $role = strtolower((string) $portal->role);
        if (in_array($role, ['admin', 'super_admin', 'director', 'manager', 'owner'], true)) {
            return;
        }
        if ($role === 'customer' || $role === '' || $role === 'applicant') {
            $portal->role = 'staff';
            $portal->password_hash = Hash::make($plain);
            $portal->status = 'active';
            $portal->save();
        }
    }
}
