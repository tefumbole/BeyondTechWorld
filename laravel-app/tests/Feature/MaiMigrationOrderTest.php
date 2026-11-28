<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaiMigrationOrderTest extends TestCase
{
    public function test_a_fresh_database_keeps_signins_when_both_migrations_run()
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_26_233000_create_mai_customer_sessions.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_28_120000_mai_dashboard_tokens.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_28_130000_create_mai_signins.php', '--force' => true]);
        DB::table('mai_signins')->insert([
            'user_id' => 4,
            'signed_in_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(Schema::hasTable('mai_signins'));
        $this->assertTrue(Schema::hasTable('mai_operator_actions'));
        $this->assertTrue(Schema::hasColumn('mai_api_keys', 'mai_session_id'));
        $this->assertSame(1, DB::table('mai_signins')->count());
    }

    public function test_the_token_migration_keeps_signins_that_already_exist()
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_26_233000_create_mai_customer_sessions.php', '--force' => true]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_28_130000_create_mai_signins.php', '--force' => true]);
        DB::table('mai_signins')->insert([
            'user_id' => 9,
            'signed_in_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_28_120000_mai_dashboard_tokens.php', '--force' => true]);

        $this->assertSame(1, DB::table('mai_signins')->where('user_id', 9)->count());
        $this->assertTrue(Schema::hasColumn('mai_api_keys', 'mai_session_id'));
        $this->assertTrue(Schema::hasTable('mai_operator_actions'));
    }
}
