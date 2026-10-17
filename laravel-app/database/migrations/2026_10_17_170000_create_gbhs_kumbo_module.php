<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateGbhsKumboModule extends Migration
{
    public function up()
    {
        Schema::create('gbhs_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('hero_title')->nullable();
            $table->string('hero_subtitle')->nullable();
            $table->string('hero_image')->nullable();
            $table->string('logo')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('president_name')->nullable();
            $table->string('president_title')->nullable();
            $table->string('president_signature')->nullable();
            $table->unsignedInteger('letter_organization_id')->nullable();
            $table->boolean('show_provisional')->default(false);
            $table->timestamps();
        });

        Schema::create('gbhs_sections', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('slug');
            $table->string('title');
            $table->string('kind')->default('page');
            $table->text('body')->nullable();
            $table->string('image')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('gbhs_leaders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('gbhs_documents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('title');
            $table->string('file')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('gbhs_batches', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedSmallInteger('year');
            $table->string('name');
            $table->string('status')->default('unconfirmed');
            $table->string('representative_name')->nullable();
            $table->string('representative_phone')->nullable();
            $table->string('representative_email')->nullable();
            $table->unsignedInteger('contribution_target')->nullable();
            $table->string('whatsapp_group_jid')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'year']);
        });

        Schema::create('gbhs_batch_users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('batch_id')->index();
            $table->timestamps();
            $table->unique(['user_id', 'batch_id']);
        });

        Schema::create('gbhs_bylaw_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('version');
            $table->date('effective_on')->nullable();
            $table->string('file')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('gbhs_applications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('reference')->unique();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->unsignedInteger('batch_id')->nullable()->index();
            $table->unsignedInteger('bylaw_version_id')->nullable();
            $table->string('status')->default('draft');
            $table->string('phone')->nullable();
            $table->boolean('phone_verified')->default(false);
            $table->string('full_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('years_attended')->nullable();
            $table->string('profession')->nullable();
            $table->string('employer')->nullable();
            $table->string('email')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('photo')->nullable();
            $table->text('signature_path')->nullable();
            $table->text('declaration')->nullable();
            $table->boolean('agree_bylaws')->default(false);
            $table->boolean('consent_processing')->default(false);
            $table->boolean('consent_announcements')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('reviewer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('letter_id')->nullable();
            $table->unsignedInteger('member_id')->nullable();
            $table->timestamps();
        });

        Schema::create('gbhs_members', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('application_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->unsignedInteger('batch_id')->nullable()->index();
            $table->string('membership_number')->unique();
            $table->string('status')->default('active');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_group_memberships', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id')->index();
            $table->unsignedInteger('customer_group_id')->index();
            $table->timestamps();
            $table->unique(['customer_id', 'customer_group_id'], 'customer_group_membership_unique');
        });

        Schema::create('gbhs_contributions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('batch_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable()->index();
            $table->unsignedInteger('project_id')->nullable()->index();
            $table->unsignedInteger('payment_id')->nullable()->index();
            $table->string('contributor_name')->nullable();
            $table->string('classification');
            $table->unsignedInteger('amount')->default(0);
            $table->unsignedInteger('in_kind_qty')->nullable();
            $table->unsignedInteger('in_kind_value')->nullable();
            $table->string('currency', 8)->default('XAF');
            $table->boolean('verified')->default(false);
            $table->boolean('historical')->default(false);
            $table->boolean('anonymous')->default(false);
            $table->string('source_note', 500)->nullable();
            $table->string('receipt')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gbhs_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('public_token')->unique();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('batch_id')->nullable();
            $table->unsignedInteger('project_id')->nullable();
            $table->string('donor_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 8)->default('XAF');
            $table->string('method');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('anonymous')->default(false);
            $table->string('message', 500)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });

        Schema::create('gbhs_projects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('target')->nullable();
            $table->string('status')->default('planned');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('committee')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('gbhs_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->string('place')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('gbhs_deliveries', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('letter_id')->nullable()->index();
            $table->string('target');
            $table->string('kind')->default('group');
            $table->string('status')->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['letter_id', 'target']);
        });

        Schema::create('gbhs_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('gbhs_audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('summary', 500);
            $table->timestamps();
        });

        $names = [
            'gbhs_module', 'gbhs.manage', 'gbhs.membership', 'gbhs.finance',
            'gbhs.communications', 'gbhs.batch', 'gbhs.member',
        ];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach ($names as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Exception $e) {
                }
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('gbhs_audit_logs');
        Schema::dropIfExists('gbhs_messages');
        Schema::dropIfExists('gbhs_deliveries');
        Schema::dropIfExists('gbhs_events');
        Schema::dropIfExists('gbhs_projects');
        Schema::dropIfExists('gbhs_payments');
        Schema::dropIfExists('gbhs_contributions');
        Schema::dropIfExists('customer_group_memberships');
        Schema::dropIfExists('gbhs_members');
        Schema::dropIfExists('gbhs_applications');
        Schema::dropIfExists('gbhs_bylaw_versions');
        Schema::dropIfExists('gbhs_batch_users');
        Schema::dropIfExists('gbhs_batches');
        Schema::dropIfExists('gbhs_documents');
        Schema::dropIfExists('gbhs_leaders');
        Schema::dropIfExists('gbhs_sections');
        Schema::dropIfExists('gbhs_settings');
    }
}
