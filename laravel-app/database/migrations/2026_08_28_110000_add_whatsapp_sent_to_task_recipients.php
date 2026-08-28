<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddWhatsappSentToTaskRecipients extends Migration
{
    public function up()
    {
        if (Schema::hasTable('task_assignments') && ! Schema::hasColumn('task_assignments', 'whatsapp_sent')) {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->boolean('whatsapp_sent')->default(false)->after('invite_token');
            });
        }

        if (Schema::hasTable('task_cc') && ! Schema::hasColumn('task_cc', 'whatsapp_sent')) {
            Schema::table('task_cc', function (Blueprint $table) {
                $table->boolean('whatsapp_sent')->default(false)->after('user_id');
            });
        }

        // Older rows were already attempted in-request. Re-queue the last 36 hours so
        // assignees/CCs who were dropped by Wasender rate limits get the message.
        $since = now()->subHours(36);
        if (Schema::hasColumn('task_assignments', 'whatsapp_sent')) {
            DB::table('task_assignments')->where('created_at', '<', $since)->update(['whatsapp_sent' => 1]);
            DB::table('task_assignments')->where('created_at', '>=', $since)->update(['whatsapp_sent' => 0]);
        }
        if (Schema::hasColumn('task_cc', 'whatsapp_sent')) {
            DB::table('task_cc')->where('created_at', '<', $since)->update(['whatsapp_sent' => 1]);
            DB::table('task_cc')->where('created_at', '>=', $since)->update(['whatsapp_sent' => 0]);
        }
        if (Schema::hasColumn('tasks', 'notifications_sent')) {
            DB::table('tasks')
                ->where('created_at', '>=', $since)
                ->where(function ($q) {
                    $q->where('is_scheduled', 0)->orWhereNull('is_scheduled');
                })
                ->update(['notifications_sent' => 0]);
        }
    }

    public function down()
    {
        if (Schema::hasTable('task_assignments') && Schema::hasColumn('task_assignments', 'whatsapp_sent')) {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->dropColumn('whatsapp_sent');
            });
        }
        if (Schema::hasTable('task_cc') && Schema::hasColumn('task_cc', 'whatsapp_sent')) {
            Schema::table('task_cc', function (Blueprint $table) {
                $table->dropColumn('whatsapp_sent');
            });
        }
    }
}
