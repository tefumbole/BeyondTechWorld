<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff and interns recovering their account choose a username. Before this the
 * chosen name was written over users.name, which is the person's display name in
 * payroll, timesheets and letters. Give them a real username column instead.
 */
class AddUsernameToUsers extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username', 100)->nullable()->after('name');
            });
        }

        // MySQL allows repeated NULLs under a unique index, so unset usernames stay valid.
        try {
            DB::statement('CREATE UNIQUE INDEX users_username_unique ON users (username)');
        } catch (\Throwable $e) {
            // Index already present.
        }
    }

    public function down()
    {
        try {
            DB::statement('DROP INDEX users_username_unique ON users');
        } catch (\Throwable $e) {
        }

        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('username');
            });
        }
    }
}
