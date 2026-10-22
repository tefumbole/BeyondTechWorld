<?php

namespace Tests\Unit;

use App\Support\AppTimezone;
use PHPUnit\Framework\TestCase;

class AppTimezoneTest extends TestCase
{
    public function test_an_unchanged_timezone_does_not_need_a_writable_env_file()
    {
        $path = $this->envFile("APP_TIMEZONE=Africa/Douala\n");
        chmod($path, 0444);

        $this->assertNull(AppTimezone::persist('Africa/Douala', $path));
        $this->assertStringContainsString('APP_TIMEZONE=Africa/Douala', file_get_contents($path));

        chmod($path, 0644);
        @unlink($path);
    }

    public function test_a_locked_env_file_does_not_throw_away_the_settings_save()
    {
        $path = $this->envFile("APP_TIMEZONE=UTC\n");
        chmod($path, 0444);

        $notice = AppTimezone::persist('Africa/Douala', $path);

        $this->assertNotNull($notice);
        $this->assertStringContainsString('system name was saved', $notice);
        $this->assertStringContainsString('APP_TIMEZONE=UTC', file_get_contents($path));

        chmod($path, 0644);
        @unlink($path);
    }

    public function test_a_changed_timezone_is_written_when_the_file_is_writable()
    {
        $path = $this->envFile("APP_NAME=Test\n");

        $this->assertNull(AppTimezone::persist('Africa/Douala', $path));
        $this->assertStringContainsString('APP_TIMEZONE=Africa/Douala', file_get_contents($path));

        @unlink($path);
    }

    protected function envFile($contents)
    {
        $path = sys_get_temp_dir().'/btw-env-'.uniqid('', true);
        file_put_contents($path, $contents);

        return $path;
    }
}
