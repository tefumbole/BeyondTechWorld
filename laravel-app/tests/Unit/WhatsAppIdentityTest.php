<?php

namespace Tests\Unit;

use App\Customer;
use App\Employee;
use App\Services\WhatsApp\WhatsAppIdentityService;
use App\User;
use Tests\WhatsAppHubTestCase;

class WhatsAppIdentityTest extends WhatsAppHubTestCase
{
    public function test_unknown_number_returns_empty()
    {
        $this->assertSame([], app(WhatsAppIdentityService::class)->resolve('670000000'));
    }

    public function test_known_user_customer_employee()
    {
        User::create([
            'name' => 'Ada', 'email' => 'ada@example.test', 'password' => bcrypt('x'),
            'phone' => '670111222', 'role_id' => 1, 'is_active' => true, 'is_deleted' => false,
        ]);
        Employee::create(['name' => 'Ada E', 'phone_number' => '670111222', 'is_active' => true]);
        Customer::create(['name' => 'Ada C', 'phone_number' => '670111222', 'is_active' => true]);

        $roles = array_column(app(WhatsAppIdentityService::class)->resolve('+237670111222'), 'role');
        $this->assertContains('user', $roles);
        $this->assertContains('employee', $roles);
        $this->assertContains('customer', $roles);
    }
}
