<?php

namespace Tests\Unit;

use App\Services\WhatsApp\WhatsAppLeadClassifier;
use App\WhatsApp\LeadCatalog;
use Tests\WhatsAppHubTestCase;

class WhatsAppLeadClassifierTest extends WhatsAppHubTestCase
{
    public function test_greetings_and_noise_are_not_meaningful()
    {
        $c = new WhatsAppLeadClassifier();
        foreach (['hi', 'Hello', 'thanks', 'how have you been', 'ok', '🙏'] as $body) {
            $this->assertFalse($c->isMeaningful($body), $body);
        }
    }

    public function test_wedding_sound_led_is_classified()
    {
        $c = new WhatsAppLeadClassifier();
        $body = 'I need sound and LED screens for a wedding';
        $this->assertTrue($c->isMeaningful($body));
        $this->assertContains($c->category($body), [
            LeadCatalog::CAT_AUDIO,
            LeadCatalog::CAT_LED_SCREEN,
            LeadCatalog::CAT_EVENT_PRODUCTION,
        ]);
    }

    public function test_request_without_keyword_can_still_qualify()
    {
        $c = new WhatsAppLeadClassifier();
        $this->assertTrue($c->isMeaningful('Do you have this available for Saturday?'));
    }
}
