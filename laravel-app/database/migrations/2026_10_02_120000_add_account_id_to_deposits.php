<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountIdToDeposits extends Migration
{
    public function up()
    {
        if (Schema::hasTable('deposits') && ! Schema::hasColumn('deposits', 'account_id')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->unsignedInteger('account_id')->nullable()->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('deposits') && Schema::hasColumn('deposits', 'account_id')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }
    }
}
