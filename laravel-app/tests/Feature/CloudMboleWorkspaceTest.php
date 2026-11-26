<?php

namespace Tests\Feature;

use App\Cloud\CloudTenant;
use App\Services\Cloud\CloudMbolePrice;
use App\Services\Cloud\CloudWhatsAppConnectService;
use App\Support\SiteMenu;
use Tests\TestCase;

class CloudMboleWorkspaceTest extends TestCase
{
    public function test_country_price_and_number_match()
    {
        $price = new CloudMbolePrice();
        $this->assertSame('+237670000000', $price->e164('670000000', '237'));
        $this->assertSame('+250780000000', $price->e164('0780000000', '250'));
        $this->assertSame('+250794006160', $price->e164('237794006160', '250'));
        $this->assertSame('+33670000000', $price->e164('670000000', '33'));

        $cameroon = new CloudTenant();
        $cameroon->phone = '237670000000';
        $this->assertSame(['amount' => '5000.00', 'currency' => 'XAF'], $price->forMessaging($cameroon, 'MOMO'));
        $this->assertSame(['amount' => '10.00', 'currency' => 'USD'], $price->forMessaging($cameroon, 'VISA'));

        $rwanda = new CloudTenant();
        $rwanda->phone = '+250780000000';
        config(['cloud.rwanda_momo' => false]);
        $card = $price->forMessaging($rwanda, 'VISA');
        $this->assertSame('10.00', $card['amount']);
        $this->assertSame('USD', $card['currency']);

        $service = app(CloudWhatsAppConnectService::class);
        $this->assertTrue($service->phonesMatch('+237670000000', '237670000000'));
        $this->assertFalse($service->phonesMatch('+237670000000', '+250780000000'));
        $this->assertFalse($service->phonesMatch('', '237670000000'));
    }

    public function test_main_menu_opens_whatsapp_mai()
    {
        $this->assertSame('Whats MAI', SiteMenu::landingItems()['whatsapp-mai']);
        $this->assertArrayNotHasKey('apply', SiteMenu::landingItems());
        $order = SiteMenu::landingOrder();
        $reviews = array_search('reviews', $order, true);
        $mai = array_search('whatsapp-mai', $order, true);
        $this->assertTrue($reviews !== false && $mai === $reviews - 1);
        $this->assertSame('Verification code', \App\WhatsApp\WhatsAppMessage::listPreview("*AUTHENTICATION*\n————"));

    }

    public function test_rwanda_momo_stays_closed_until_a_collector_exists()
    {
        $this->expectException(\RuntimeException::class);
        $rwanda = new CloudTenant();
        $rwanda->phone = '250780000000';
        config(['cloud.rwanda_momo' => false]);
        (new CloudMbolePrice())->forMessaging($rwanda, 'MOMO');
    }
}
