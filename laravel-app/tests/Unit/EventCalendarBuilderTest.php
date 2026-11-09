<?php

namespace Tests\Unit;

use App\Services\EventCalendarBuilder;
use Carbon\Carbon;
use Tests\TestCase;

class EventCalendarBuilderTest extends TestCase
{
    public function test_multi_day_event_spreads_across_the_days_it_covers()
    {
        $event = new \stdClass();
        $event->id = 'bible';
        $event->name = 'Bible Conference';
        $event->internal_status = 'planning';
        $event->event_start_at = Carbon::parse('2026-11-04 00:00:00');
        $event->event_end_at = Carbon::parse('2026-11-07 23:59:00');

        $weeks = (new EventCalendarBuilder())->weeks(
            [$event],
            Carbon::parse('2026-11-01', 'Africa/Douala')->startOfMonth()
        );

        $bar = $this->barNamed($weeks, 'Bible Conference');
        $this->assertNotNull($bar);
        $this->assertSame(4, $bar['span']);
        $this->assertFalse($bar['continues_before']);
        $this->assertFalse($bar['continues_after']);
    }

    public function test_event_continues_onto_the_next_week()
    {
        $event = new \stdClass();
        $event->id = 'span-week';
        $event->name = 'Site Validation';
        $event->internal_status = 'draft';
        $event->event_start_at = Carbon::parse('2026-11-06 00:00:00');
        $event->event_end_at = Carbon::parse('2026-11-10 23:59:00');

        $weeks = (new EventCalendarBuilder())->weeks(
            [$event],
            Carbon::parse('2026-11-01', 'Africa/Douala')->startOfMonth()
        );

        $spans = [];
        foreach ($weeks as $week) {
            foreach ($week['bars'] as $bar) {
                if ($bar['name'] === 'Site Validation') {
                    $spans[] = $bar;
                }
            }
        }

        $this->assertCount(2, $spans);
        $this->assertSame(3, $spans[0]['span']);
        $this->assertTrue($spans[0]['continues_after']);
        $this->assertSame(2, $spans[1]['span']);
        $this->assertTrue($spans[1]['continues_before']);
    }

    public function test_setup_and_unpacking_days_are_marked_separately()
    {
        $event = new \stdClass();
        $event->id = 'festival';
        $event->name = 'Bamenda Community Festival';
        $event->internal_status = 'planning';
        $event->event_start_at = Carbon::parse('2026-11-19 09:00:00');
        $event->event_end_at = Carbon::parse('2026-11-22 18:00:00');
        $event->setup_dates = json_encode([
            ['date' => '2026-11-17', 'note' => ''],
            ['date' => '2026-11-18', 'note' => ''],
        ]);
        $event->unpack_dates = json_encode([
            ['date' => '2026-11-23', 'note' => ''],
        ]);

        $weeks = (new EventCalendarBuilder())->weeks(
            [$event],
            Carbon::parse('2026-11-01', 'Africa/Douala')->startOfMonth()
        );

        $setup = $this->barNamed($weeks, 'Setup · Bamenda Community Festival');
        $unpack = null;
        foreach ($weeks as $week) {
            foreach ($week['bars'] as $bar) {
                if ($bar['name'] === 'Unpack · Bamenda Community Festival') {
                    $unpack = $bar;
                }
            }
        }
        $this->assertNotNull($setup);
        $this->assertSame('setup', $setup['kind']);
        $this->assertSame(2, $setup['span']);
        $this->assertSame('#c6ab47', $setup['color']);
        $this->assertNotNull($unpack);
        $this->assertSame('unpack', $unpack['kind']);
        $this->assertSame(1, $unpack['span']);
        $this->assertSame('#0f766e', $unpack['color']);
    }

    protected function barNamed(array $weeks, $name)
    {
        foreach ($weeks as $week) {
            foreach ($week['bars'] as $bar) {
                if ($bar['name'] === $name && $bar['span'] > 1) {
                    return $bar;
                }
            }
        }

        return null;
    }
}
