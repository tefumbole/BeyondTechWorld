<?php

namespace App\Services;

use Carbon\Carbon;

class EventCalendarBuilder
{
    public function monthStart($month)
    {
        if (! is_string($month) || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::now('Africa/Douala')->format('Y-m');
        }

        return Carbon::parse($month.'-01 00:00:00')->startOfMonth();
    }

    public function weeks($events, Carbon $monthStart)
    {
        $monthStart = Carbon::parse($monthStart->format('Y-m-01').' 00:00:00')->startOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);
        $palette = ['#0b3f90', '#7c3aed', '#db2777', '#0f766e', '#b45309', '#0369a1', '#be123c', '#15803d'];
        $weeks = [];
        $cursor = $gridStart->copy();

        while ($cursor->lte($gridEnd)) {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $days[] = $cursor->copy();
                $cursor->addDay();
            }
            $occupied = [];
            $bars = [];
            foreach ($events as $event) {
                $start = $this->day(isset($event->event_start_at) ? $event->event_start_at : null);
                if (! $start) {
                    continue;
                }
                $end = $this->day(isset($event->event_end_at) ? $event->event_end_at : null);
                if (! $end || $end->lt($start)) {
                    $end = $start->copy();
                }
                if ($end->lt($days[0]) || $start->gt($days[6])) {
                    continue;
                }
                $barStart = $start->lt($days[0]) ? $days[0]->copy() : $start->copy();
                $barEnd = $end->gt($days[6]) ? $days[6]->copy() : $end->copy();
                $col = $this->dayIndex($barStart, $days[0]);
                $span = $this->dayIndex($barEnd, $barStart) + 1;
                $lane = 0;
                while (true) {
                    if (! isset($occupied[$lane])) {
                        $occupied[$lane] = array_fill(0, 7, false);
                    }
                    $busy = false;
                    for ($c = $col; $c < $col + $span; $c++) {
                        if ($occupied[$lane][$c]) {
                            $busy = true;
                            break;
                        }
                    }
                    if (! $busy) {
                        for ($c = $col; $c < $col + $span; $c++) {
                            $occupied[$lane][$c] = true;
                        }
                        break;
                    }
                    $lane++;
                }
                $id = (string) $event->id;
                $bars[] = [
                    'id' => $id,
                    'name' => (string) $event->name,
                    'status' => (string) (isset($event->internal_status) ? $event->internal_status : ''),
                    'col' => $col,
                    'span' => $span,
                    'lane' => $lane,
                    'continues_before' => $start->lt($days[0]),
                    'continues_after' => $end->gt($days[6]),
                    'color' => $palette[abs(crc32($id)) % count($palette)],
                ];
            }
            $weeks[] = [
                'days' => $days,
                'bars' => $bars,
                'lanes' => max(1, count($occupied)),
            ];
        }

        return $weeks;
    }

    protected function day($value)
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse(Carbon::parse($value)->format('Y-m-d').' 00:00:00');
    }

    protected function dayIndex(Carbon $later, Carbon $earlier)
    {
        return (int) round(($later->copy()->startOfDay()->timestamp - $earlier->copy()->startOfDay()->timestamp) / 86400);
    }
}
