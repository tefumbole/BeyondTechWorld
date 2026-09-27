<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateEventLightingAndScreenPricing extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');
        if (Schema::hasTable('event_pricing_rules')) {
            $exists = DB::table('event_pricing_rules')->where('key', 'screen_per_m2')->exists();
            if (! $exists) {
                DB::table('event_pricing_rules')->insert([
                    'key' => 'screen_per_m2',
                    'label' => 'LED screen per m²',
                    'group' => 'SCREEN',
                    'amount' => 60000,
                    'currency' => 'XAF',
                    'unit' => 'per_m2',
                    'active' => 1,
                    'metadata' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('event_pricing_rules')->where('key', 'screen_per_m2')->update([
                    'amount' => 60000,
                    'label' => 'LED screen per m²',
                    'unit' => 'per_m2',
                    'active' => 1,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('event_packages')) {
            $updates = [
                ['category' => 'LIGHTING', 'code' => 'BASIC', 'name' => 'Basic Lights', 'description' => 'Basic Lights (No Moving heads) — par/wash wash lighting without moving heads.', 'icon' => '💡'],
                ['category' => 'LIGHTING', 'code' => 'STANDARD', 'name' => 'Standard Lights', 'description' => 'Standard Lights — Par Lights with Par Robots.', 'icon' => '✨'],
                ['category' => 'LIGHTING', 'code' => 'PREMIUM', 'name' => 'Premium Lights', 'description' => 'Premium (All Lights) — full lighting package including moving heads and premium fixtures.', 'icon' => '🌟'],
                ['category' => 'SOUND_MODE', 'code' => 'PLAYBACK', 'name' => 'Playback', 'description' => 'Basic sound / playback only — no live instruments.', 'icon' => '🎵'],
                ['category' => 'SOUND_MODE', 'code' => 'PLAYBACK_PIANO', 'name' => 'Piano Bar', 'description' => 'Playback + Piano Bar setup.', 'icon' => '🎹'],
                ['category' => 'SOUND_MODE', 'code' => 'FULL_LIVE', 'name' => 'Full Setup', 'description' => 'Full live band / instrument setup.', 'icon' => '🎸'],
            ];
            foreach ($updates as $row) {
                DB::table('event_packages')
                    ->where('category', $row['category'])
                    ->where('code', $row['code'])
                    ->update([
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'icon' => $row['icon'],
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    public function down()
    {
        // Non-destructive: keep updated copy.
    }
}
