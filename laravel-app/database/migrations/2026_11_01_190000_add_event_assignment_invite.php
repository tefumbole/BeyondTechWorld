<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventAssignmentInvite extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('event_assignments')) {
            return;
        }
        Schema::table('event_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('event_assignments', 'invite_token')) {
                $table->string('invite_token', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('event_assignments', 'acceptance_status')) {
                $table->string('acceptance_status', 20)->default('pending');
            }
            if (! Schema::hasColumn('event_assignments', 'accepted_at')) {
                $table->dateTime('accepted_at')->nullable();
            }
            if (! Schema::hasColumn('event_assignments', 'invited_at')) {
                $table->dateTime('invited_at')->nullable();
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('event_assignments')) {
            return;
        }
        Schema::table('event_assignments', function (Blueprint $table) {
            foreach (['invite_token', 'acceptance_status', 'accepted_at', 'invited_at'] as $column) {
                if (Schema::hasColumn('event_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
