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
