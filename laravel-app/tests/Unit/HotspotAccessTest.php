<?php

namespace Tests\Unit;

use App\Hotspot\HotspotLimits;
use App\Hotspot\RouterOsClient;
use PHPUnit\Framework\TestCase;

class HotspotAccessTest extends TestCase
{
    public function test_rate_and_duration_formats()
    {
        $this->assertSame('1M/2M', HotspotLimits::rateLimit(1024, 2048));
        $this->assertSame('512k/5M', HotspotLimits::rateLimit(512, 5120));
        $this->assertSame('30m', HotspotLimits::duration(1800));
        $this->assertSame('1h', HotspotLimits::duration(3600));
        $this->assertSame(3723, HotspotLimits::uptimeSeconds('1h2m3s'));
    }

    public function test_browser_success_does_not_grant_access()
    {
        $this->assertFalse(HotspotLimits::mayActivate(false, 'SUCCESSFUL'));
        $this->assertFalse(HotspotLimits::mayActivate(null, 'SUCCESSFUL'));
        $this->assertTrue(HotspotLimits::mayActivate(true, 'SUCCESSFUL'));
    }

    public function test_time_and_data_end_for_different_reasons()
    {
        $now = new \DateTimeImmutable('2026-10-15 12:00:00');
        $past = new \DateTimeImmutable('2026-10-15 11:00:00');
        $future = new \DateTimeImmutable('2026-10-15 13:00:00');

        $this->assertSame('time', HotspotLimits::endReason($past, $now, 10, 1000));
        $this->assertSame('data', HotspotLimits::endReason($future, $now, 1000, 1000));
        $this->assertNull(HotspotLimits::endReason($future, $now, 10, 1000));
        $this->assertNull(HotspotLimits::endReason($future, $now, 10, null));
    }

    public function test_management_host_must_be_private()
    {
        $this->assertTrue(HotspotLimits::acceptsManagementHost('10.200.0.2'));
        $this->assertTrue(HotspotLimits::acceptsManagementHost('192.168.88.1'));
        $this->assertFalse(HotspotLimits::acceptsManagementHost('8.8.8.8'));
        $this->assertFalse(HotspotLimits::acceptsManagementHost('beyondtechworld.com'));
    }

    public function test_voucher_codes_are_single_use_shaped()
    {
        $seen = [];
        for ($i = 0; $i < 20; $i++) {
            $code = HotspotLimits::voucherCode();
            $this->assertSame(10, strlen($code));
            $this->assertSame(0, preg_match('/[01IO]/', $code));
            $this->assertArrayNotHasKey($code, $seen);
            $seen[$code] = true;
        }
    }

    public function test_api_word_length_encoding()
    {
        $this->assertSame(chr(4), RouterOsClient::encodeLength(4));
        $this->assertSame(chr(0x80 | 0x01).chr(0x00), RouterOsClient::encodeLength(256));
    }
}
