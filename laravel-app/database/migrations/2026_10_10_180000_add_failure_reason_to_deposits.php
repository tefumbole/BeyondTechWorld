<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFailureReasonToDeposits extends Migration
{
    public function up()
    {
        if (Schema::hasTable('deposits') && ! Schema::hasColumn('deposits', 'failure_reason')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->string('failure_reason', 180)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('deposits') && Schema::hasColumn('deposits', 'failure_reason')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->dropColumn('failure_reason');
            });
        }
    }
}
