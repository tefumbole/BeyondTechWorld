<?php

namespace Tests\Unit;

use App\Gbhs\GbhsHistory;
use App\Gbhs\GbhsRules;
use Tests\TestCase;

class GbhsRulesTest extends TestCase
{
    public function test_personal_details_wait_for_a_verified_phone()
    {
        $this->assertFalse(GbhsRules::mayStorePersonal(false));
        $this->assertTrue(GbhsRules::mayStorePersonal(true));
    }

    public function test_an_existing_pricing_group_is_kept()
    {
        $this->assertSame(5, GbhsRules::pricingGroupId(5, 9));
        $this->assertSame(9, GbhsRules::pricingGroupId(null, 9));
    }

    public function test_a_second_approval_does_not_create_another_member()
    {
        $this->assertTrue(GbhsRules::shouldCreateMember(null));
        $this->assertFalse(GbhsRules::shouldCreateMember(4));
    }

    public function test_the_1991_reversal_is_outside_received_totals()
    {
        $summary = GbhsRules::summarize(GbhsHistory::rows());
        $this->assertSame(152000, $summary['cancelled']);
        $reversal = ['classification' => 'reversal', 'amount' => 152000];
        $this->assertFalse(GbhsRules::countsAsReceived($reversal));
        $this->assertGreaterThan(0, $summary['difference']);
        $this->assertSame(0, $summary['verified']);
    }

    public function test_a_browser_return_does_not_count_as_payment()
    {
        $this->assertFalse(GbhsRules::paidFromBrowserReturn(false));
        $this->assertTrue(GbhsRules::paidFromBrowserReturn(true));
    }

    public function test_each_group_is_sent_once()
    {
        $targets = GbhsRules::groupTargets(
            ['120@g.us', '120@g.us', 'not-a-group', '121@g.us'],
            ['120@g.us']
        );
        $this->assertSame(['121@g.us'], $targets);
    }
}
