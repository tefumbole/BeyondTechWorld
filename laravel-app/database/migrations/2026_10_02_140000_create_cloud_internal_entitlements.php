<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform entitlement for INTERNAL companies. Not a subscription table.
 */
class CreateCloudInternalEntitlements extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cloud_internal_entitlements') || ! Schema::hasTable('cloud_tenants')) {
            return;
        }

        Schema::create('cloud_internal_entitlements', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('cloud_module_id');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'cloud_module_id'], 'cloud_internal_entitlement_unique');
            $table->foreign('cloud_tenant_id')->references('id')->on('cloud_tenants')->onDelete('cascade');
            $table->foreign('cloud_module_id')->references('id')->on('cloud_modules')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cloud_internal_entitlements');
    }
}
