<?php

namespace App\Services\Cloud;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A company signup may reuse a phone number that already belongs to a client
 * or a staff account. The username must still be free, because everyone signs
 * in through the same portal.
 */
class CloudSignupIdentity
{
    public function usernameTaken($username)
    {
        $username = strtolower(trim((string) $username));
        if ($username === '' || ! Schema::hasTable('users')) {
            return false;
        }
        if (Schema::hasColumn('users', 'username')) {
            if (User::whereRaw('LOWER(username) = ?', [$username])->exists()) {
                return true;
            }
        }
        if (Schema::hasColumn('users', 'name') && User::whereRaw('LOWER(name) = ?', [$username])->exists()) {
            return true;
        }
        if (Schema::hasTable('be_users') && Schema::hasColumn('be_users', 'username')) {
            if (DB::table('be_users')->whereRaw('LOWER(username) = ?', [$username])->exists()) {
                return true;
            }
        }

        return false;
    }
}
