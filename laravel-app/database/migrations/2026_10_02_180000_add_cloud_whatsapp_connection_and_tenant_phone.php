<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One WhatsApp connection per company, and one phone number per company.
 * Messages stay owned through the conversation.
 */
class AddCloudWhatsappConnectionAndTenantPhone extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cloud_whatsapp_connections')) {
            Schema::create('cloud_whatsapp_connections', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->string('provider', 32);
                $table->string('provider_connection_id', 191)->nullable();
                $table->string('phone_number', 32)->nullable();
                $table->string('display_name', 191)->nullable();
                $table->string('status', 32)->default('ACTIVE');
                $table->string('credentials_reference', 191)->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('last_health_check_at')->nullable();
                $table->timestamps();
                $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('restrict');
                $table->index(['provider', 'provider_connection_id'], 'cloud_wa_provider_connection_index');
            });
        }

        if (! Schema::hasTable('whatsapp_contacts') || ! Schema::hasColumn('whatsapp_contacts', 'cloud_tenant_id')) {
            return;
        }
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropUnique('whatsapp_contacts_normalized_phone_unique');
        });
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->unique(['cloud_tenant_id', 'normalized_phone'], 'whatsapp_contacts_tenant_phone_unique');
        });
    }

    public function down()
    {
        if (Schema::hasTable('whatsapp_contacts') && Schema::hasColumn('whatsapp_contacts', 'normalized_phone')) {
            Schema::table('whatsapp_contacts', function (Blueprint $table) {
                $table->dropUnique('whatsapp_contacts_tenant_phone_unique');
            });
            Schema::table('whatsapp_contacts', function (Blueprint $table) {
                $table->unique('normalized_phone');
            });
        }
        Schema::dropIfExists('cloud_whatsapp_connections');
    }
}
