<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPayoutRequesterPhone extends Migration
{
    public function up()
    {
        if (Schema::hasTable('campay_payout_requests') && ! Schema::hasColumn('campay_payout_requests', 'requester_phone')) {
            Schema::table('campay_payout_requests', function (Blueprint $table) {
                $table->string('requester_phone', 32)->nullable()->after('requester_name');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('campay_payout_requests') && Schema::hasColumn('campay_payout_requests', 'requester_phone')) {
            Schema::table('campay_payout_requests', function (Blueprint $table) {
                $table->dropColumn('requester_phone');
            });
        }
    }
}
