<?php

namespace Tests\Feature;

use App\Product;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\ServiceMenu;
use App\Services\Event\EventSolutionBuilderService;
use App\Services\Event\StagePricingService;
use Illuminate\Support\Facades\Schema;
use Tests\WhatsAppHubTestCase;

class EventSolutionBuilderTest extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_27_103000_create_event_packages_and_pricing.php',
            '--force' => true,
        ]);
    }

    public function test_service_menu_sound_is_event_first_not_behringer_catalogue()
    {
        if (Schema::hasTable('products')) {
            Product::create([
                'name' => 'BEHRINGER SINGLE BASS SPEAKER PASSIVE',
                'code' => 'BHR-1',
                'type' => 'standard',
                'cost' => 0,
                'price' => 1000,
                'qty' => 5,
                'is_active' => true,
            ]);
        }
        $payload = app(ServiceMenu::class)->describePayload('sound', '1');
        $this->assertStringNotContainsString('BEHRINGER', strtoupper($payload['reply']));
        $this->assertStringContainsString('Playback', $payload['reply']);
        $this->assertNotEmpty($payload['choices']);
    }

    public function test_vague_speaker_availability_redirects_to_event_solution()
    {
        $exec = app(AssistantToolExecutor::class);
        $result = $exec->execute('check_rental_availability', [
            'query' => 'speakers',
            'event_date' => '2026-09-30',
            'qty' => 1,
        ], ['roles' => []]);
        $this->assertTrue(! empty($result['event_first']) || ! empty($result['redirect']));
        $this->assertStringNotContainsString('BEHRINGER', strtoupper(json_encode($result)));
    }

    public function test_stage_math_is_deterministic()
    {
        $svc = app(StagePricingService::class);
        $four = $svc->calculate(4, 4);
        $this->assertSame(16.0, (float) $four['area_m2']);
        $this->assertSame(640000.0, (float) $four['price']);

        $six = $svc->calculate(6, 4);
        $this->assertSame(24.0, (float) $six['area_m2']);
        $this->assertSame(960000.0, (float) $six['price']);

        $parsed = $svc->parseDimensions('4 by 4');
        $this->assertSame([4.0, 4.0], $parsed);
    }

    public function test_build_event_solution_does_not_force_single_sku()
    {
        $solution = app(EventSolutionBuilderService::class)->build([
            'event_type' => 'wedding',
            'event_date' => '2026-09-30',
            'venue' => 'Bamenda Congress Hall',
            'guest_count' => 500,
            'sound_mode' => 'FULL_LIVE',
            'sound_package' => 'STANDARD',
            'lighting_package' => 'STANDARD',
            'want_screen' => false,
            'stage_length_m' => 4,
            'stage_width_m' => 4,
            'truss_package' => 'WITH_ROOF',
            'within_town' => true,
        ]);
        $this->assertTrue($solution['success']);
        $this->assertTrue($solution['multiple_events_per_day_supported']);
        $this->assertStringContainsString('Bamenda Congress Hall', $solution['summary_text']);
        $this->assertStringContainsString('640,000', $solution['summary_text']);
        $keys = array_column($solution['commercial_lines'], 'key');
        $this->assertContains('SOUND', $keys);
        $this->assertContains('LIGHTING', $keys);
        $this->assertContains('STAGE', $keys);
        $this->assertContains('TRUSS', $keys);
    }

    public function test_calculate_stage_price_tool()
    {
        $result = app(AssistantToolExecutor::class)->execute('calculate_stage_price', [
            'length_m' => '8',
            'width_m' => '6',
        ], ['roles' => []]);
        $this->assertTrue(! empty($result['success']));
        $this->assertSame(48.0, (float) $result['area_m2']);
        $this->assertSame(1920000.0, (float) $result['price']);
    }

    public function test_screen_math_is_deterministic()
    {
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_27_110000_update_event_lighting_and_screen_pricing.php',
            '--force' => true,
        ]);
        $svc = app(\App\Services\Event\ScreenPricingService::class);
        $threeByTwo = $svc->calculate(3, 2);
        $this->assertSame(6.0, (float) $threeByTwo['area_m2']);
        $this->assertSame(360000.0, (float) $threeByTwo['price']);

        $byArea = $svc->calculateArea(6);
        $this->assertSame(6.0, (float) $byArea['area_m2']);
        $this->assertSame(360000.0, (float) $byArea['price']);

        $this->assertSame([3.0, 2.0], $svc->parseDimensions('height 3 width 2'));
        $areaOnly = $svc->parseSize('6 m²');
        $this->assertSame(6.0, (float) $areaOnly['area']);
        $this->assertStringContainsString('Height and Width', $svc->sizePrompt());
    }

    public function test_sound_options_are_numbered_and_clickable()
    {
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_09_27_110000_update_event_lighting_and_screen_pricing.php',
            '--force' => true,
        ]);
        $ui = app(\App\Services\Event\EventOptionPresentation::class)->soundUi();
        $this->assertSame('radio', $ui['mode']);
        $this->assertCount(3, $ui['options']);
        $this->assertStringStartsWith('1.', $ui['options'][0]['label']);
        $resolved = app(\App\Services\Event\EventOptionPresentation::class)->resolveIncomingChoice('SOUND_MODE', '2');
        $this->assertSame('sound_mode:playback_piano', $resolved);
        $inferred = app(\App\Services\Event\EventOptionPresentation::class)->inferUiFromReply(
            "What sound experience are you looking for?\n1. Playback\n2. Piano Bar\n3. Full Setup"
        );
        $this->assertNotNull($inferred);
        $this->assertSame('SOUND_MODE', $inferred['category']);
    }

    public function test_extras_checkbox_ui()
    {
        $ui = app(\App\Services\Event\EventPackageCatalogService::class)->extrasCheckboxGroup();
        $this->assertSame('checkbox', $ui['mode']);
        $values = array_column($ui['options'], 'value');
        $this->assertContains('lights', $values);
        $this->assertContains('screens', $values);
        $this->assertContains('stage', $values);
    }

    public function test_playback_maps_to_basic_sound_package()
    {
        $solution = app(EventSolutionBuilderService::class)->build([
            'event_type' => 'wedding',
            'event_date' => '2026-09-30',
            'sound_mode' => 'PLAYBACK',
            'within_town' => true,
        ]);
        $sound = null;
        foreach ($solution['commercial_lines'] as $row) {
            if (($row['key'] ?? '') === 'SOUND') {
                $sound = $row;
                break;
            }
        }
        $this->assertNotNull($sound);
        $this->assertSame('BASIC', $sound['code'] ?? null);
    }
}
