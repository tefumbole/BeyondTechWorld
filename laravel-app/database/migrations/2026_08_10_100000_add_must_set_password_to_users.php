<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMustSetPasswordToUsers extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'must_set_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('must_set_password')->default(false)->after('otp_verify');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'must_set_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('must_set_password');
            });
        }
    }
}
