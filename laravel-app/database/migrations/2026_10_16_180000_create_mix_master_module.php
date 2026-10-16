<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateMixMasterModule extends Migration
{
    public function up()
    {
        Schema::create('mix_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('mix_instruments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('category_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('mix_manufacturers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('mix_consoles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('manufacturer_id')->nullable()->index();
            $table->string('name');
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('channel_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mix_presets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('instrument_id')->nullable()->index();
            $table->unsignedInteger('console_id')->nullable()->index();
            $table->unsignedInteger('manufacturer_id')->nullable()->index();
            $table->string('collection')->default('beyond');
            $table->string('name');
            $table->string('slug');
            $table->string('source_label')->nullable();
            $table->text('notes')->nullable();
            $table->string('official_url')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('mix_preset_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('preset_id')->index();
            $table->unsignedSmallInteger('version_number');
            $table->string('label')->nullable();
            $table->text('notes')->nullable();
            $table->text('input_json')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('mix_eq_bands', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('version_id')->index();
            $table->unsignedSmallInteger('band_index');
            $table->boolean('enabled')->default(true);
            $table->string('filter_type')->default('bell');
            $table->decimal('frequency_hz', 10, 2)->nullable();
            $table->decimal('gain_db', 8, 2)->nullable();
            $table->decimal('q', 8, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('mix_dynamics', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('version_id')->index();
            $table->string('kind');
            $table->boolean('enabled')->default(true);
            $table->decimal('threshold_db', 8, 2)->nullable();
            $table->decimal('ratio', 8, 2)->nullable();
            $table->decimal('attack_ms', 8, 2)->nullable();
            $table->decimal('release_ms', 8, 2)->nullable();
            $table->string('knee')->nullable();
            $table->decimal('makeup_db', 8, 2)->nullable();
            $table->decimal('hold_ms', 8, 2)->nullable();
            $table->decimal('range_db', 8, 2)->nullable();
            $table->string('key_source')->nullable();
            $table->string('key_filter')->nullable();
            $table->string('model')->nullable();
            $table->string('expander_mode')->nullable();
            $table->text('extra_json')->nullable();
            $table->timestamps();
        });

        Schema::create('mix_effects', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('version_id')->index();
            $table->string('effect_type');
            $table->decimal('send_level', 8, 2)->nullable();
            $table->decimal('return_level', 8, 2)->nullable();
            $table->text('params_json')->nullable();
            $table->timestamps();
        });

        Schema::create('mix_routings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('version_id')->index();
            $table->string('destination');
            $table->string('label')->nullable();
            $table->string('pre_post')->nullable();
            $table->decimal('level_db', 8, 2)->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('mix_buses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('bus_type');
            $table->unsignedInteger('preset_version_id')->nullable();
            $table->decimal('limiter_threshold_db', 8, 2)->nullable();
            $table->decimal('limiter_release_ms', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('mix_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('console_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('channel_count')->default(32);
            $table->text('notes')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedInteger('current_version_id')->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('mix_template_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('template_id')->index();
            $table->unsignedSmallInteger('version_number');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mix_template_channels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('template_version_id')->index();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->unsignedInteger('instrument_id')->nullable();
            $table->unsignedInteger('preset_version_id')->nullable();
            $table->unsignedInteger('bus_id')->nullable();
            $table->unsignedSmallInteger('stereo_link')->nullable();
            $table->timestamps();
        });

        Schema::create('mix_shows', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('console_id')->nullable()->index();
            $table->unsignedInteger('template_version_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('event_type')->nullable();
            $table->string('version_label')->nullable();
            $table->text('main_notes')->nullable();
            $table->text('monitor_notes')->nullable();
            $table->text('subwoofer_notes')->nullable();
            $table->text('bus_notes')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('mix_mastering_presets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->text('chain_json')->nullable();
            $table->decimal('target_lufs', 6, 2)->nullable();
            $table->decimal('true_peak_db', 6, 2)->nullable();
            $table->string('output_format')->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->unsignedSmallInteger('bit_depth')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'slug']);
        });

        Schema::create('mix_import_files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('status')->default('review');
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
        });

        Schema::create('mix_import_reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('import_file_id')->index();
            $table->string('preset_key');
            $table->string('preset_name');
            $table->string('instrument_slug');
            $table->string('field');
            $table->text('extracted_value')->nullable();
            $table->string('status');
            $table->text('note')->nullable();
            $table->text('confirmed_value')->nullable();
            $table->boolean('confirmed')->default(false);
            $table->timestamps();
        });

        Schema::create('mix_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'key']);
        });

        Schema::create('mix_audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedInteger('subject_id')->nullable();
            $table->text('body')->nullable();
            $table->timestamps();
        });

        $names = ['mix_module', 'mix.view', 'mix.manage', 'mix.publish'];
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
        Schema::dropIfExists('mix_audit_logs');
        Schema::dropIfExists('mix_settings');
        Schema::dropIfExists('mix_import_reviews');
        Schema::dropIfExists('mix_import_files');
        Schema::dropIfExists('mix_mastering_presets');
        Schema::dropIfExists('mix_shows');
        Schema::dropIfExists('mix_template_channels');
        Schema::dropIfExists('mix_template_versions');
        Schema::dropIfExists('mix_templates');
        Schema::dropIfExists('mix_buses');
        Schema::dropIfExists('mix_routings');
        Schema::dropIfExists('mix_effects');
        Schema::dropIfExists('mix_dynamics');
        Schema::dropIfExists('mix_eq_bands');
        Schema::dropIfExists('mix_preset_versions');
        Schema::dropIfExists('mix_presets');
        Schema::dropIfExists('mix_consoles');
        Schema::dropIfExists('mix_manufacturers');
        Schema::dropIfExists('mix_instruments');
        Schema::dropIfExists('mix_categories');
    }
}
