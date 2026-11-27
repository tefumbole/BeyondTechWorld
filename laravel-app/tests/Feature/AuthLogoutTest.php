<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    public function test_sign_out_on_mai_ends_the_login_and_opens_a_new_session()
    {
        $user = new User();
        $user->id = 41;
        $user->role_id = 5;
        $user->is_active = 1;
        $this->actingAs($user);
        $previous = session()->getId();
        session(['signed_in_marker' => 'yes']);

        $response = $this->get('http://mai.beyondtechworld.com/logout');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/whatsapp-mai', $location);
        $this->assertStringNotContainsString('whatsapp/chats', $location);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertGuest();
        $this->assertNull(session('signed_in_marker'));
        $this->assertNotSame($previous, session()->getId());

        $expired = false;
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'beyond_enterprise_session' && $cookie->getExpiresTime() > 0 && $cookie->getExpiresTime() < time()) {
                $expired = true;
            }
        }
        $this->assertTrue($expired);
    }

    public function test_sign_out_on_the_main_site_still_leaves_the_portal()
    {
        $user = new User();
        $user->id = 42;
        $user->role_id = 1;
        $user->is_active = 1;
        $this->actingAs($user);

        $this->get('/logout')->assertRedirect(route('beyond.home'));
        $this->assertGuest();
    }
}
