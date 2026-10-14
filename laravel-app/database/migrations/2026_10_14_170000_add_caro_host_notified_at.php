<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCaroHostNotifiedAt extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('caro_invitations') || Schema::hasColumn('caro_invitations', 'host_notified_at')) {
            return;
        }

        Schema::table('caro_invitations', function (Blueprint $table) {
            $table->timestamp('host_notified_at')->nullable();
        });
    }

    public function down()
    {
        if (! Schema::hasTable('caro_invitations') || ! Schema::hasColumn('caro_invitations', 'host_notified_at')) {
            return;
        }

        Schema::table('caro_invitations', function (Blueprint $table) {
            $table->dropColumn('host_notified_at');
        });
    }
}
