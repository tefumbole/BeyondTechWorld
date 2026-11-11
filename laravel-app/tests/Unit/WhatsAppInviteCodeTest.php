<?php

namespace Tests\Unit;

use App\Services\BeyondWasenderService;
use Tests\TestCase;

class WhatsAppInviteCodeTest extends TestCase
{
    public function test_invite_code_reads_a_group_link()
    {
        $codes = new BeyondWasenderService();

        $this->assertSame('AbCdEf12GhIj', $codes->inviteCode('https://chat.whatsapp.com/AbCdEf12GhIj'));
        $this->assertSame('AbCdEf12GhIj', $codes->inviteCode('https://chat.whatsapp.com/invite/AbCdEf12GhIj'));
        $this->assertSame('AbCdEf12GhIj', $codes->inviteCode('AbCdEf12GhIj'));
        $this->assertSame('', $codes->inviteCode('not a link'));
    }
}
