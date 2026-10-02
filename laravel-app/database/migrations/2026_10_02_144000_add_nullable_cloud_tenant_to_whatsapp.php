<?php

use App\Services\Cloud\CloudTenantColumn;
use Illuminate\Database\Migrations\Migration;

/**
 * Group D. Nullable tenant on WhatsApp contacts and conversations.
 * Does not change the global unique key on whatsapp_contacts.normalized_phone.
 * Does not add a WhatsApp connection table and does not tenant-scope messages.
 */
class AddNullableCloudTenantToWhatsapp extends Migration
{
    public function up()
    {
        foreach (['whatsapp_contacts', 'whatsapp_conversations'] as $table) {
            CloudTenantColumn::add($table);
        }
    }

    public function down()
    {
        foreach (['whatsapp_conversations', 'whatsapp_contacts'] as $table) {
            CloudTenantColumn::remove($table);
        }
    }
}
