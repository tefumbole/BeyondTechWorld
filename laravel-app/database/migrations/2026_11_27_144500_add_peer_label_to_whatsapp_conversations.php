<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPeerLabelToWhatsappConversations extends Migration
{
    public function up()
    {
        if (Schema::hasTable('whatsapp_conversations') && ! Schema::hasColumn('whatsapp_conversations', 'peer_label')) {
            Schema::table('whatsapp_conversations', function (Blueprint $table) {
                $table->string('peer_label', 191)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('whatsapp_conversations') && Schema::hasColumn('whatsapp_conversations', 'peer_label')) {
            Schema::table('whatsapp_conversations', function (Blueprint $table) {
                $table->dropColumn('peer_label');
            });
        }
    }
}
