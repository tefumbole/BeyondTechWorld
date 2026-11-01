<?php

namespace Tests\Unit;

use App\Services\CampayPayoutService;
use Tests\TestCase;

class CampayPayoutConfirmationTest extends TestCase
{
    public function test_a_payout_is_delivered_only_when_the_operator_reference_is_present()
    {
        $service = new CampayPayoutService();

        $this->assertFalse($service->payoutDelivered([
            'status' => 'SUCCESSFUL',
            'amount' => 5000,
            'currency' => 'XAF',
        ], 5000));

        $this->assertTrue($service->payoutDelivered([
            'status' => 'SUCCESSFUL',
            'amount' => 5000,
            'currency' => 'XAF',
            'operator_reference' => 'OP123',
        ], 5000));

        $this->assertFalse($service->payoutDelivered([
            'status' => 'SUCCESSFUL',
            'amount' => 100,
            'currency' => 'XAF',
            'operator_reference' => 'OP123',
        ], 5000));
    }
}
