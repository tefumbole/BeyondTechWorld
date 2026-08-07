<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSignRequestTypeToUsers extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'sign_request_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('sign_request_type', 20)->nullable()->after('sign_request_token');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'sign_request_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('sign_request_type');
            });
        }
    }
}
