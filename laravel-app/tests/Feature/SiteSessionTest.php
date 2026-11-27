<?php

namespace Tests\Feature;

use App\Support\AuthIntended;
use App\Support\SiteSession;
use Tests\TestCase;

class SiteSessionTest extends TestCase
{
    public function test_a_beyond_link_keeps_the_same_browser_login()
    {
        config(['app.url' => 'https://beyondtechworld.com', 'session.domain' => null, 'session.lifetime' => 120]);

        $this->assertFalse(SiteSession::apply('localhost'));
        $this->assertNull(config('session.domain'));

        $this->assertTrue(SiteSession::apply('mai.beyondtechworld.com'));
        $this->assertSame('.beyondtechworld.com', config('session.domain'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertTrue(config('session.secure'));
        $this->assertSame(60 * 24 * 30, (int) config('session.lifetime'));

        $this->assertSame('/whatsapp-mai', AuthIntended::safePath('https://mai.beyondtechworld.com/whatsapp-mai'));
        $this->assertSame('/admin/payments?id=1', AuthIntended::safePath('https://www.beyondtechworld.com/admin/payments?id=1'));
        $this->assertSame('/admin', AuthIntended::safePath('https://beyondtechworld.com/admin'));
        $this->assertNull(AuthIntended::safePath('https://example.com/admin'));
    }
}
