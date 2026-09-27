<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateEventPackagesAndPricing extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('event_pricing_rules')) {
            Schema::create('event_pricing_rules', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key', 64)->unique();
                $table->string('label', 120);
                $table->string('group', 64)->nullable();
                $table->decimal('amount', 15, 2)->default(0);
                $table->string('currency', 8)->default('XAF');
                $table->string('unit', 32)->nullable(); // flat|per_m2|per_day
                $table->boolean('active')->default(true);
                $table->text('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('event_packages')) {
            Schema::create('event_packages', function (Blueprint $table) {
                $table->increments('id');
                $table->string('category', 32); // SOUND|LIGHTING|SCREEN|STAGE|TRUSS|TRANSPORT
                $table->string('code', 64);
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->decimal('base_price', 15, 2)->default(0);
                $table->string('currency', 8)->default('XAF');
                $table->unsignedInteger('duration_days')->default(1);
                $table->boolean('active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('icon', 16)->nullable();
                $table->text('metadata')->nullable();
                $table->timestamps();
                $table->unique(['category', 'code']);
            });
        }

        if (! Schema::hasTable('event_package_components')) {
            Schema::create('event_package_components', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('event_package_id');
                $table->string('component_type', 32)->default('CATEGORY'); // CATEGORY|PRODUCT
                $table->string('category_key', 64)->nullable();
                $table->unsignedInteger('product_id')->nullable();
                $table->unsignedInteger('qty')->default(1);
                $table->boolean('required')->default(true);
                $table->string('search_query', 120)->nullable();
                $table->timestamps();
                $table->index('event_package_id');
            });
        }

        if (Schema::hasTable('quotations') && ! Schema::hasColumn('quotations', 'workflow_state')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->string('workflow_state', 64)->nullable()->after('quotation_status');
            });
        }

        $this->seedDefaults();
    }

    public function down()
    {
        Schema::dropIfExists('event_package_components');
        Schema::dropIfExists('event_packages');
        Schema::dropIfExists('event_pricing_rules');
        if (Schema::hasTable('quotations') && Schema::hasColumn('quotations', 'workflow_state')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('workflow_state');
            });
        }
    }

    protected function seedDefaults()
    {
        $now = date('Y-m-d H:i:s');
        $rules = [
            ['key' => 'stage_per_m2', 'label' => 'Stage per m²', 'group' => 'STAGE', 'amount' => 40000, 'unit' => 'per_m2'],
            ['key' => 'truss_without_roof', 'label' => 'Truss without roof', 'group' => 'TRUSS', 'amount' => 300000, 'unit' => 'flat'],
            ['key' => 'truss_with_roof', 'label' => 'Truss with roof', 'group' => 'TRUSS', 'amount' => 500000, 'unit' => 'flat'],
            ['key' => 'transport_basic', 'label' => 'Transport basic (within town)', 'group' => 'TRANSPORT', 'amount' => 60000, 'unit' => 'flat'],
            ['key' => 'transport_standard', 'label' => 'Transport standard (within town)', 'group' => 'TRANSPORT', 'amount' => 60000, 'unit' => 'flat'],
            ['key' => 'transport_premium', 'label' => 'Transport premium (within town)', 'group' => 'TRANSPORT', 'amount' => 120000, 'unit' => 'flat'],
        ];
        foreach ($rules as $rule) {
            if (DB::table('event_pricing_rules')->where('key', $rule['key'])->exists()) {
                continue;
            }
            DB::table('event_pricing_rules')->insert(array_merge($rule, [
                'currency' => 'XAF',
                'active' => 1,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $packages = [
            ['category' => 'SOUND', 'code' => 'BASIC', 'name' => 'Basic Sound', 'description' => 'Entry sound package for a simple one-day event.', 'base_price' => 100000, 'sort_order' => 1, 'icon' => '🔊'],
            ['category' => 'SOUND', 'code' => 'STANDARD', 'name' => 'Standard Sound', 'description' => 'Balanced sound package for medium events.', 'base_price' => 200000, 'sort_order' => 2, 'icon' => '🔊🔊'],
            ['category' => 'SOUND', 'code' => 'PREMIUM', 'name' => 'Premium Sound', 'description' => 'Higher-capacity sound package for large or live events.', 'base_price' => 300000, 'sort_order' => 3, 'icon' => '🔊🔊🔊'],
            ['category' => 'LIGHTING', 'code' => 'BASIC', 'name' => 'Basic Lighting', 'description' => 'Par lights and wash lights.', 'base_price' => 50000, 'sort_order' => 1, 'icon' => '💡'],
            ['category' => 'LIGHTING', 'code' => 'STANDARD', 'name' => 'Standard Lighting', 'description' => 'Par lights and par robots.', 'base_price' => 150000, 'sort_order' => 2, 'icon' => '✨'],
            ['category' => 'LIGHTING', 'code' => 'PREMIUM', 'name' => 'Premium Lighting', 'description' => 'Par lights, robots, par robots and washers.', 'base_price' => 300000, 'sort_order' => 3, 'icon' => '🌟'],
            ['category' => 'LIGHTING', 'code' => 'NONE', 'name' => 'No Lighting', 'description' => 'No lighting package.', 'base_price' => 0, 'sort_order' => 4, 'icon' => '🚫'],
            ['category' => 'TRUSS', 'code' => 'WITHOUT_ROOF', 'name' => 'Truss without Roof', 'description' => 'Truss structure without roof.', 'base_price' => 300000, 'sort_order' => 1, 'icon' => '🏗️'],
            ['category' => 'TRUSS', 'code' => 'WITH_ROOF', 'name' => 'Truss with Roof', 'description' => 'Truss structure with roof.', 'base_price' => 500000, 'sort_order' => 2, 'icon' => '🏟️'],
            ['category' => 'TRUSS', 'code' => 'NONE', 'name' => 'No Truss', 'description' => 'No truss.', 'base_price' => 0, 'sort_order' => 3, 'icon' => '🚫'],
            ['category' => 'SOUND_MODE', 'code' => 'PLAYBACK', 'name' => 'Playback — No Live Instruments', 'description' => 'DJ / laptop / phone playback. No full live band setup.', 'base_price' => 0, 'sort_order' => 1, 'icon' => '🎵'],
            ['category' => 'SOUND_MODE', 'code' => 'PLAYBACK_PIANO', 'name' => 'Playback + Piano Bar', 'description' => 'Playback plus piano/keyboard performance setup.', 'base_price' => 0, 'sort_order' => 2, 'icon' => '🎹'],
            ['category' => 'SOUND_MODE', 'code' => 'FULL_LIVE', 'name' => 'Full Live Setup', 'description' => 'Live musicians/band — FOH, monitors, mics, DI boxes as needed.', 'base_price' => 0, 'sort_order' => 3, 'icon' => '🎸'],
        ];
        foreach ($packages as $pkg) {
            if (DB::table('event_packages')->where('category', $pkg['category'])->where('code', $pkg['code'])->exists()) {
                continue;
            }
            DB::table('event_packages')->insert(array_merge($pkg, [
                'currency' => 'XAF',
                'duration_days' => 1,
                'active' => 1,
                'metadata' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $components = [
            'SOUND|BASIC' => [
                ['category_key' => 'SPEAKER', 'search_query' => 'speaker', 'qty' => 2],
                ['category_key' => 'MIXER', 'search_query' => 'mixer', 'qty' => 1],
                ['category_key' => 'MICROPHONE', 'search_query' => 'microphone', 'qty' => 2],
            ],
            'SOUND|STANDARD' => [
                ['category_key' => 'SPEAKER', 'search_query' => 'speaker', 'qty' => 4],
                ['category_key' => 'SUBWOOFER', 'search_query' => 'subwoofer', 'qty' => 2],
                ['category_key' => 'MIXER', 'search_query' => 'mixer', 'qty' => 1],
                ['category_key' => 'MICROPHONE', 'search_query' => 'microphone', 'qty' => 4],
            ],
            'SOUND|PREMIUM' => [
                ['category_key' => 'LINE_ARRAY', 'search_query' => 'line array', 'qty' => 4],
                ['category_key' => 'SUBWOOFER', 'search_query' => 'subwoofer', 'qty' => 4],
                ['category_key' => 'MIXER', 'search_query' => 'mixer', 'qty' => 1],
                ['category_key' => 'MICROPHONE', 'search_query' => 'microphone', 'qty' => 6],
                ['category_key' => 'MONITOR', 'search_query' => 'monitor', 'qty' => 2],
            ],
            'LIGHTING|BASIC' => [
                ['category_key' => 'PAR_LIGHT', 'search_query' => 'par light', 'qty' => 4],
                ['category_key' => 'WASH_LIGHT', 'search_query' => 'wash', 'qty' => 2],
            ],
            'LIGHTING|STANDARD' => [
                ['category_key' => 'PAR_LIGHT', 'search_query' => 'par light', 'qty' => 6],
                ['category_key' => 'PAR_ROBOT', 'search_query' => 'par robot', 'qty' => 4],
            ],
            'LIGHTING|PREMIUM' => [
                ['category_key' => 'PAR_LIGHT', 'search_query' => 'par light', 'qty' => 8],
                ['category_key' => 'ROBOT_LIGHT', 'search_query' => 'robot', 'qty' => 4],
                ['category_key' => 'PAR_ROBOT', 'search_query' => 'par robot', 'qty' => 4],
                ['category_key' => 'WASH_LIGHT', 'search_query' => 'wash', 'qty' => 4],
            ],
        ];
        foreach ($components as $key => $items) {
            list($cat, $code) = explode('|', $key);
            $pkgId = DB::table('event_packages')->where('category', $cat)->where('code', $code)->value('id');
            if (! $pkgId) {
                continue;
            }
            foreach ($items as $item) {
                $exists = DB::table('event_package_components')
                    ->where('event_package_id', $pkgId)
                    ->where('category_key', $item['category_key'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('event_package_components')->insert([
                    'event_package_id' => $pkgId,
                    'component_type' => 'CATEGORY',
                    'category_key' => $item['category_key'],
                    'product_id' => null,
                    'qty' => $item['qty'],
                    'required' => 1,
                    'search_query' => $item['search_query'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
