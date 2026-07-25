<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateContractsModuleTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('contract_types')) {
            Schema::create('contract_types', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('code', 40)->unique();
                $table->string('name');
                $table->string('category', 80)->nullable();
                $table->string('default_party_a_label', 120)->default('Party A');
                $table->string('default_party_b_label', 120)->default('Party B');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_templates')) {
            Schema::create('contract_templates', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('type_id', 36)->index();
                $table->string('name');
                $table->string('code', 60)->unique();
                $table->text('description')->nullable();
                $table->string('current_version_id', 36)->nullable();
                $table->string('layout_id', 60)->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_template_versions')) {
            Schema::create('contract_template_versions', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('template_id', 36)->index();
                $table->unsignedInteger('version_no')->default(1);
                $table->longText('content_html')->nullable();
                $table->longText('content_json')->nullable();
                $table->longText('placeholder_schema')->nullable();
                $table->longText('signature_workflow_json')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->timestamp('published_at')->nullable();
                $table->unsignedBigInteger('published_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('number', 40)->unique();
                $table->string('type_id', 36)->nullable()->index();
                $table->string('template_id', 36)->nullable()->index();
                $table->string('template_version_id', 36)->nullable();
                $table->string('title');
                $table->string('status', 40)->default('draft')->index();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->string('current_revision_id', 36)->nullable();
                $table->date('effective_date')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('value', 15, 2)->nullable();
                $table->string('currency', 10)->nullable()->default('XAF');
                $table->string('jurisdiction')->nullable();
                $table->string('purpose')->nullable();
                $table->text('payment_schedule')->nullable();
                $table->string('primary_link_type', 60)->nullable();
                $table->string('primary_link_id', 36)->nullable();
                $table->timestamp('signature_expires_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('superseded_by', 36)->nullable();
                $table->string('supersedes', 36)->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->index(['primary_link_type', 'primary_link_id']);
            });
        }

        if (! Schema::hasTable('contract_revisions')) {
            Schema::create('contract_revisions', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->unsignedInteger('revision_no')->default(1);
                $table->longText('content_html')->nullable();
                $table->longText('content_json')->nullable();
                $table->longText('resolved_data_json')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->string('state', 40)->default('draft'); // draft|frozen|superseded
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('frozen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_parties')) {
            Schema::create('contract_parties', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('side', 10); // A|B
                $table->string('subject_type', 40)->nullable();
                $table->string('subject_id', 36)->nullable();
                $table->string('role_label', 120)->nullable();
                $table->longText('identity_snapshot_json')->nullable();
                $table->longText('representative_snapshot_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_signatories')) {
            Schema::create('contract_signatories', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('revision_id', 36)->nullable()->index();
                $table->string('role', 40); // party_a|party_b|admin|witness_a|witness_b
                $table->string('party_id', 36)->nullable();
                $table->string('person_type', 40)->nullable();
                $table->string('person_id', 36)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('display_name')->nullable();
                $table->unsignedTinyInteger('stage')->default(1);
                $table->boolean('required')->default(true);
                $table->string('status', 40)->default('pending'); // pending|signed|declined|revoked
                $table->timestamp('signed_at')->nullable();
                $table->text('signature_image')->nullable();
                $table->string('typed_name')->nullable();
                $table->text('declined_reason')->nullable();
                $table->string('ip_address', 60)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_witnesses')) {
            Schema::create('contract_witnesses', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('for_party', 10); // A|B
                $table->string('person_type', 40)->nullable();
                $table->string('person_id', 36)->nullable();
                $table->longText('identity_snapshot_json')->nullable();
                $table->string('signatory_id', 36)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_links')) {
            Schema::create('contract_links', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('link_type', 60);
                $table->string('link_id', 36);
                $table->string('relationship', 60)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->index(['link_type', 'link_id']);
            });
        }

        if (! Schema::hasTable('contract_values')) {
            Schema::create('contract_values', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('revision_id', 36)->nullable()->index();
                $table->string('placeholder_key', 120);
                $table->longText('value_json')->nullable();
                $table->string('source_type', 60)->nullable();
                $table->string('source_id', 36)->nullable();
                $table->boolean('manually_overridden')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_attachments')) {
            Schema::create('contract_attachments', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('revision_id', 36)->nullable();
                $table->string('file_path')->nullable();
                $table->string('name')->nullable();
                $table->string('kind', 60)->nullable();
                $table->boolean('incorporated')->default(false);
                $table->string('checksum', 64)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('signature_requests')) {
            Schema::create('signature_requests', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('signatory_id', 36)->index();
                $table->string('token_hash', 64)->index();
                $table->string('channel', 40)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('signature_events')) {
            Schema::create('signature_events', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('revision_id', 36)->nullable();
                $table->string('signatory_id', 36)->nullable();
                $table->string('event_type', 60);
                $table->timestamp('event_at')->useCurrent();
                $table->string('actor_type', 40)->nullable();
                $table->string('actor_id', 36)->nullable();
                $table->string('ip_address', 60)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->longText('metadata_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_documents')) {
            Schema::create('contract_documents', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('revision_id', 36)->nullable();
                $table->string('kind', 40); // draft|final|certificate
                $table->string('file_path')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->boolean('immutable')->default(false);
                $table->timestamp('generated_at')->nullable();
                $table->string('render_engine', 80)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_audit_logs')) {
            Schema::create('contract_audit_logs', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('actor_type', 40)->nullable();
                $table->string('actor_id', 36)->nullable();
                $table->string('action', 80);
                $table->longText('before_json')->nullable();
                $table->longText('after_json')->nullable();
                $table->string('ip_address', 60)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('contract_comments')) {
            Schema::create('contract_comments', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('contract_id', 36)->index();
                $table->string('revision_id', 36)->nullable();
                $table->unsignedBigInteger('author_id')->nullable();
                $table->text('body');
                $table->string('clause_block_id', 80)->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_settings')) {
            Schema::create('contract_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contract_rate_categories')) {
            Schema::create('contract_rate_categories', function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->string('code', 40)->unique();
                $table->string('name');
                $table->unsignedInteger('daily_rate')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        $tables = [
            'contract_rate_categories', 'contract_settings', 'contract_comments', 'contract_audit_logs',
            'contract_documents', 'signature_events', 'signature_requests', 'contract_attachments',
            'contract_values', 'contract_links', 'contract_witnesses', 'contract_signatories',
            'contract_parties', 'contract_revisions', 'contracts', 'contract_template_versions',
            'contract_templates', 'contract_types',
        ];
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }
}
