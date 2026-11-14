<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAntivirusRequests extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('antivirus_settings')) {
            Schema::create('antivirus_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->unsignedSmallInteger('campaign_year')->default(2027);
                $table->string('chief_name', 191)->nullable();
                $table->string('chief_phone', 32)->nullable();
                $table->string('chief_role', 191)->nullable();
                $table->timestamps();
                $table->unique('cloud_tenant_id', 'antivirus_settings_tenant');
            });
        }

        if (! Schema::hasTable('antivirus_requests')) {
            Schema::create('antivirus_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->string('reference', 32)->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('status', 32)->default('draft');
                $table->string('payment_status', 32)->default('none');
                $table->string('receipt_status', 32)->default('none');
                $table->string('requester_phone', 32);
                $table->string('requester_name', 191);
                $table->string('requester_name_source', 32)->default('manual');
                $table->string('requester_role', 64)->nullable();
                $table->string('requester_role_other', 191)->nullable();
                $table->string('region', 64)->nullable();
                $table->string('station', 191)->nullable();
                $table->unsignedInteger('quantity')->nullable();
                $table->text('department_lines')->nullable();
                $table->string('selfie_path', 255)->nullable();
                $table->string('signature_path', 255)->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->string('sign_fingerprint', 64)->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('station_approved_at')->nullable();
                $table->timestamp('forwarded_at')->nullable();
                $table->timestamp('finance_acknowledged_at')->nullable();
                $table->string('chief_name', 191)->nullable();
                $table->string('chief_phone', 32)->nullable();
                $table->timestamp('chief_phone_verified_at')->nullable();
                $table->string('chief_signature_path', 255)->nullable();
                $table->timestamp('chief_signed_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->string('decline_reason', 500)->nullable();
                $table->string('declined_by', 191)->nullable();
                $table->date('payment_date')->nullable();
                $table->decimal('payment_amount', 12, 2)->nullable();
                $table->string('payment_reference', 191)->nullable();
                $table->timestamp('payment_recorded_at')->nullable();
                $table->unsignedInteger('receipt_quantity')->nullable();
                $table->string('receipt_note', 500)->nullable();
                $table->timestamp('receipt_phone_verified_at')->nullable();
                $table->timestamp('receipt_confirmed_at')->nullable();
                $table->string('pdf_path', 255)->nullable();
                $table->string('pdf_token_hash', 64)->nullable();
                $table->timestamp('pdf_expires_at')->nullable();
                $table->string('receipt_token_hash', 64)->nullable();
                $table->timestamp('receipt_expires_at')->nullable();
                $table->text('approvers_json')->nullable();
                $table->timestamps();
                $table->unique('reference', 'antivirus_requests_reference');
                $table->index('pdf_token_hash', 'antivirus_requests_pdf_token');
                $table->index('receipt_token_hash', 'antivirus_requests_receipt_token');
                $table->index(['cloud_tenant_id', 'status'], 'antivirus_requests_status');
                $table->index(['cloud_tenant_id', 'year', 'region'], 'antivirus_requests_year_region');
            });
        }

        if (! Schema::hasTable('antivirus_approvers')) {
            Schema::create('antivirus_approvers', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->unsignedInteger('antivirus_request_id');
                $table->unsignedTinyInteger('position');
                $table->string('role', 64);
                $table->string('role_other', 191)->nullable();
                $table->string('phone', 32);
                $table->string('name', 191);
                $table->string('name_source', 32)->default('manual');
                $table->timestamp('name_confirmed_at')->nullable();
                $table->string('token_hash', 64);
                $table->timestamp('link_expires_at')->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('signature_path', 255)->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->string('decline_reason', 500)->nullable();
                $table->timestamps();
                $table->unique('token_hash', 'antivirus_approvers_token');
                $table->index(['antivirus_request_id', 'position'], 'antivirus_approvers_position');
            });
        }

        if (! Schema::hasTable('antivirus_handoffs')) {
            Schema::create('antivirus_handoffs', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->unsignedInteger('antivirus_request_id');
                $table->string('token_hash', 64);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();
                $table->unique('token_hash', 'antivirus_handoffs_token');
                $table->index('antivirus_request_id', 'antivirus_handoffs_request');
            });
        }

        if (! Schema::hasTable('antivirus_activities')) {
            Schema::create('antivirus_activities', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->unsignedInteger('antivirus_request_id');
                $table->string('event', 191);
                $table->string('detail', 500)->nullable();
                $table->timestamps();
                $table->index('antivirus_request_id', 'antivirus_activities_request');
            });
        }

        if (! Schema::hasTable('antivirus_notices')) {
            Schema::create('antivirus_notices', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('cloud_tenant_id');
                $table->unsignedInteger('antivirus_request_id');
                $table->string('kind', 64);
                $table->string('phone', 32);
                $table->text('body');
                $table->string('document_path', 255)->nullable();
                $table->string('document_name', 191)->nullable();
                $table->string('status', 32)->default('queued');
                $table->timestamp('text_sent_at')->nullable();
                $table->timestamp('document_sent_at')->nullable();
                $table->timestamps();
                $table->unique(['antivirus_request_id', 'kind', 'phone'], 'antivirus_notices_once');
                $table->index('status', 'antivirus_notices_status');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('antivirus_notices');
        Schema::dropIfExists('antivirus_activities');
        Schema::dropIfExists('antivirus_handoffs');
        Schema::dropIfExists('antivirus_approvers');
        Schema::dropIfExists('antivirus_requests');
        Schema::dropIfExists('antivirus_settings');
    }
}
