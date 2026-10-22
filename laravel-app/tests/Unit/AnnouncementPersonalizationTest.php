<?php

namespace Tests\Unit;

use App\Support\AnnouncementPersonalization;
use PHPUnit\Framework\TestCase;

class AnnouncementPersonalizationTest extends TestCase
{
    public function test_a_selected_person_is_greeted_by_name_even_when_personalize_is_off()
    {
        $announcement = (object) [
            'personalized' => false,
            'header' => '',
            'reference' => 'BCL/L-/26/0003238',
            'body' => "Dear {name},\n\nGood Morning Sir and Happy New Week.\n\nSincerely.\nTefu Roland Mbole.",
            'footer' => '',
        ];

        $message = AnnouncementPersonalization::buildMessage($announcement, [
            'name' => 'Ada Lovelace',
            'phone' => '237675321739',
        ]);

        $this->assertStringStartsWith('Dear *Ada Lovelace*,', $message);
        $this->assertStringNotContainsString('Hello,', $message);
        $this->assertStringNotContainsString('Dear {name}', $message);
        $this->assertStringNotContainsString('Dear *Ada Lovelace*,'."\n\n".'Dear', $message);
        $this->assertStringContainsString("Good Morning Sir and Happy New Week.", $message);
        $this->assertStringContainsString("_Sincerely._\n_Tefu Roland Mbole._", $message);
        $position = strpos($message, 'Good Morning Sir');
        $closing = strpos($message, '_Sincerely._');
        $this->assertNotFalse($position);
        $this->assertNotFalse($closing);
        $this->assertLessThan($closing, $position);
    }

    public function test_a_group_post_stays_a_general_greeting()
    {
        $announcement = (object) [
            'personalized' => false,
            'header' => '',
            'reference' => '',
            'body' => "Dear {name},\n\nChoir practice is at 5.",
            'footer' => 'Praise Team',
        ];

        $message = AnnouncementPersonalization::buildMessage($announcement, [
            'kind' => 'group',
            'name' => 'NBC Praise Team',
            'phone' => '',
        ]);

        $this->assertStringStartsWith('Hello,', $message);
        $this->assertStringNotContainsString('NBC Praise Team', $message);
        $this->assertStringContainsString('Choir practice is at 5.', $message);
        $this->assertStringContainsString('_Praise Team_', $message);
    }

    public function test_dear_sir_is_replaced_by_the_recipient_name()
    {
        $announcement = (object) [
            'personalized' => false,
            'header' => '',
            'reference' => '',
            'body' => "Dear Sir,\n\nPlease confirm the delivery.",
            'footer' => 'Beyond Enterprise',
        ];

        $message = AnnouncementPersonalization::buildMessage($announcement, [
            'name' => 'Ministry of Transport',
            'phone' => '237600000000',
        ]);

        $this->assertStringStartsWith('Dear *Ministry of Transport*,', $message);
        $this->assertStringNotContainsString('Dear Sir', $message);
        $this->assertStringContainsString('Please confirm the delivery.', $message);
    }

    public function test_a_programmed_reminder_greets_the_same_recipient_by_name()
    {
        $announcement = (object) [
            'personalized' => false,
            'header' => '',
            'reference' => 'BCL/L-/26/0003238',
            'body' => "Dear {name},\n\nGood Morning Sir and Happy New Week.\n\nSincerely.\nTefu Roland Mbole.",
            'footer' => '',
        ];

        $message = AnnouncementPersonalization::reminderMessage($announcement, [
            'name' => 'Ministry of Transport',
            'phone' => '237600000000',
        ]);

        $this->assertStringStartsWith('⏰ *Reminder*', $message);
        $this->assertStringContainsString('Dear *Ministry of Transport*,', $message);
        $this->assertStringNotContainsString('Hello,', $message);
        $this->assertStringContainsString('Good Morning Sir and Happy New Week.', $message);
    }
}
