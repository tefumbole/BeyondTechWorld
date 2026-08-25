<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Account recovery used to write the chosen username over users.name, which is
 * the person's real name on payslips, letters and timesheets. Move that value
 * into the new username column and put the real name back from their
 * application, so the login they set keeps working under their own name.
 *
 * Deliberately narrow: only rows whose current name has no space while the
 * application on the same email address holds a multi-word name.
 */
class RestoreNamesOverwrittenByUsernameReset extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'username') || ! Schema::hasTable('applications')) {
            return;
        }

        $candidates = DB::table('users')
            ->join('applications', function ($join) {
                $join->whereRaw('LOWER(applications.email) = LOWER(users.email)');
            })
            ->whereNull('users.username')
            ->where('users.name', 'not like', '% %')
            ->where('applications.full_name', 'like', '% %')
            ->whereRaw('LOWER(applications.full_name) <> LOWER(users.name)')
            ->where('users.is_deleted', 0)
            ->select('users.id', 'users.name', 'applications.full_name')
            ->distinct()
            ->get();

        foreach ($candidates as $row) {
            $username = strtolower(trim($row->name));
            if ($username === '') {
                continue;
            }

            $clash = DB::table('users')
                ->whereRaw('LOWER(username) = ?', [$username])
                ->where('id', '!=', $row->id)
                ->exists();

            DB::table('users')->where('id', $row->id)->update([
                'username' => $clash ? null : $username,
                'name' => $row->full_name,
                'updated_at' => now(),
            ]);

            Log::info('Restored user name overwritten by account recovery', [
                'user_id' => $row->id,
                'restored_name' => $row->full_name,
                'username' => $clash ? null : $username,
            ]);
        }
    }

    public function down()
    {
        // The original overwrite was data loss; nothing to reverse.
    }
}
