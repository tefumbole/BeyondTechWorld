<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSignRequestTokenToUsers extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'sign_request_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('sign_request_token', 64)->nullable()->unique()->after('approve');
                $table->timestamp('sign_request_expires_at')->nullable()->after('sign_request_token');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'sign_request_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['sign_request_token', 'sign_request_expires_at']);
            });
        }
    }
}
