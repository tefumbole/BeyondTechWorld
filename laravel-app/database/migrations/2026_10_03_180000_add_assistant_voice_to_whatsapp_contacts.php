<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAssistantVoiceToWhatsappContacts extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_contacts') || Schema::hasColumn('whatsapp_contacts', 'call_name')) {
            return;
        }
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('call_name', 80)->nullable();
            $table->string('relationship', 80)->nullable();
            $table->string('preferred_language', 80)->nullable();
            $table->text('voice_note')->nullable();
        });
    }

    public function down()
    {
        if (! Schema::hasTable('whatsapp_contacts') || ! Schema::hasColumn('whatsapp_contacts', 'call_name')) {
            return;
        }
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropColumn(['call_name', 'relationship', 'preferred_language', 'voice_note']);
        });
    }
}
