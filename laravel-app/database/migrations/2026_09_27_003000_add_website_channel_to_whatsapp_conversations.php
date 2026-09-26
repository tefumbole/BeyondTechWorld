<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWebsiteChannelToWhatsappConversations extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_conversations', 'channel')) {
                $table->string('channel', 20)->default('whatsapp')->after('status');
            }
            if (! Schema::hasColumn('whatsapp_conversations', 'session_token')) {
                $table->string('session_token', 64)->nullable()->unique()->after('channel');
            }
            if (! Schema::hasColumn('whatsapp_conversations', 'page_context')) {
                $table->string('page_context', 255)->nullable()->after('session_token');
            }
        });

        // Index for hub filters (ignore if already present).
        try {
            Schema::table('whatsapp_conversations', function (Blueprint $table) {
                $table->index(['channel', 'last_activity_at'], 'wa_conv_channel_activity_idx');
            });
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        if (! Schema::hasTable('whatsapp_conversations')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'page_context')) {
                $table->dropColumn('page_context');
            }
            if (Schema::hasColumn('whatsapp_conversations', 'session_token')) {
                $table->dropUnique(['session_token']);
                $table->dropColumn('session_token');
            }
            if (Schema::hasColumn('whatsapp_conversations', 'channel')) {
                try {
                    $table->dropIndex('wa_conv_channel_activity_idx');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('channel');
            }
        });
    }
}
