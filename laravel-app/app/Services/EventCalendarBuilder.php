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
                $id = (string) $event->id;
                $eventName = (string) $event->name;
                $eventColor = $palette[abs(crc32($id)) % count($palette)];
                $segments = [];
                $start = $this->day(isset($event->event_start_at) ? $event->event_start_at : null);
                if ($start) {
                    $end = $this->day(isset($event->event_end_at) ? $event->event_end_at : null);
                    if (! $end || $end->lt($start)) {
                        $end = $start->copy();
                    }
                    $segments[] = [
                        'kind' => 'event',
                        'name' => $eventName,
                        'start' => $start,
                        'end' => $end,
                        'color' => $eventColor,
                    ];
                }
                foreach ($this->dateSpans(isset($event->setup_dates) ? $event->setup_dates : null) as $span) {
                    $segments[] = [
                        'kind' => 'setup',
                        'name' => 'Setup · '.$eventName,
                        'start' => $span[0],
                        'end' => $span[1],
                        'color' => '#f97316',
                    ];
                }
                foreach ($this->dateSpans(isset($event->unpack_dates) ? $event->unpack_dates : null) as $span) {
                    $segments[] = [
                        'kind' => 'unpack',
                        'name' => 'Unpacking · '.$eventName,
                        'start' => $span[0],
                        'end' => $span[1],
                        'color' => '#06b6d4',
                    ];
                }
                foreach ($segments as $segment) {
                    $placed = $this->placeBar($segment['start'], $segment['end'], $days, $occupied);
                    if (! $placed) {
                        continue;
                    }
                    $bars[] = [
                        'id' => $id,
                        'name' => $segment['name'],
                        'kind' => $segment['kind'],
                        'status' => (string) (isset($event->internal_status) ? $event->internal_status : ''),
                        'col' => $placed['col'],
                        'span' => $placed['span'],
                        'lane' => $placed['lane'],
                        'continues_before' => $placed['continues_before'],
                        'continues_after' => $placed['continues_after'],
                        'color' => $segment['color'],
                    ];
                }
            }
            $weeks[] = [
                'days' => $days,
                'bars' => $bars,
                'lanes' => max(1, count($occupied)),
            ];
        }

        return $weeks;
    }

    public function weeksFromMarks(array $marks, Carbon $monthStart)
    {
        $monthStart = Carbon::parse($monthStart->format('Y-m-01').' 00:00:00')->startOfMonth();
        $gridStart = $monthStart->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);
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
            foreach ($marks as $mark) {
                $payload = [];
                foreach ((array) (isset($mark['dates']) ? $mark['dates'] : []) as $date) {
                    $payload[] = ['date' => $date];
                }
                foreach ($this->dateSpans($payload) as $span) {
                    $placed = $this->placeBar($span[0], $span[1], $days, $occupied);
                    if (! $placed) {
                        continue;
                    }
                    $bars[] = [
                        'id' => (string) (isset($mark['id']) ? $mark['id'] : ''),
                        'name' => (string) (isset($mark['name']) ? $mark['name'] : ''),
                        'kind' => (string) (isset($mark['kind']) ? $mark['kind'] : 'event'),
                        'status' => '',
                        'col' => $placed['col'],
                        'span' => $placed['span'],
                        'lane' => $placed['lane'],
                        'continues_before' => $placed['continues_before'],
                        'continues_after' => $placed['continues_after'],
                        'color' => (string) (isset($mark['color']) ? $mark['color'] : '#0b3f90'),
                    ];
                }
            }
            $weeks[] = [
                'days' => $days,
                'bars' => $bars,
                'lanes' => max(1, count($occupied)),
            ];
        }

        return $weeks;
    }

    public function eventDateMarks($events)
    {
        $palette = ['#0b3f90', '#7c3aed', '#db2777', '#0f766e', '#b45309', '#0369a1', '#be123c', '#15803d'];
        $marks = [];
        foreach ($events as $event) {
            $start = $this->day(isset($event->event_start_at) ? $event->event_start_at : null);
            if (! $start) {
                continue;
            }
            $end = $this->day(isset($event->event_end_at) ? $event->event_end_at : null);
            if (! $end || $end->lt($start)) {
                $end = $start->copy();
            }
            $dates = [];
            $cursor = $start->copy();
            $guard = 0;
            while ($cursor->lte($end) && $guard < 120) {
                $dates[] = $cursor->format('Y-m-d');
                $cursor->addDay();
                $guard++;
            }
            $marks[] = [
                'id' => (string) $event->id,
                'name' => (string) $event->name,
                'kind' => 'event',
                'color' => $palette[abs(crc32((string) $event->id)) % count($palette)],
                'dates' => $dates,
            ];
        }

        return $marks;
    }

    public function setupMarks($events)
    {
        return $this->dutyMarks($events, 'setup_dates', 'Setup', 'setup', '#f97316');
    }

    public function unpackingMarks($events)
    {
        return $this->dutyMarks($events, 'unpack_dates', 'Unpacking', 'unpack', '#06b6d4');
    }

    public function engineerMarks($assignments)
    {
        $palette = ['#0b3f90', '#7c3aed', '#db2777', '#0f766e', '#b45309', '#0369a1', '#be123c', '#15803d'];
        $marks = [];
        foreach ($assignments as $assignment) {
            if ((string) $assignment->acceptance_status === 'rejected') {
                continue;
            }
            $event = $assignment->event;
            if (! $event || (string) $event->internal_status === 'cancelled') {
                continue;
            }
            $profile = $assignment->workerProfile;
            if (! $profile || ! class_exists(\App\Event::class)) {
                continue;
            }
            $dates = [];
            foreach (\App\Event::parseWorkDays($assignment->work_dates) as $day) {
                if (! empty($day['date'])) {
                    $dates[] = $day['date'];
                }
            }
            if (! count($dates)) {
                continue;
            }
            $marks[] = [
                'id' => (string) $event->id,
                'name' => $profile->displayName().' · '.$event->name,
                'kind' => 'engineer',
                'color' => $palette[abs(crc32((string) $profile->id)) % count($palette)],
                'dates' => $dates,
            ];
        }

        return $marks;
    }

    public function paymentMarks($payments)
    {
        $grouped = [];
        foreach ($payments as $payment) {
            if ((string) $payment->status !== 'paid') {
                continue;
            }
            $date = $this->paymentDay($payment);
            if (! $date) {
                continue;
            }
            $profile = $payment->workerProfile;
            $name = $profile ? $profile->displayName() : 'Payment';
            $eventName = $payment->event ? $payment->event->name : '';
            $key = $date.'|'.(string) $payment->event_id.'|'.(string) $payment->worker_profile_id;
            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => (string) $payment->event_id,
                    'name' => $name,
                    'event' => $eventName,
                    'date' => $date,
                    'amount' => 0,
                ];
            }
            $grouped[$key]['amount'] += (int) $payment->amount;
        }
        $marks = [];
        foreach ($grouped as $row) {
            $label = number_format($row['amount']).' XAF · '.$row['name'];
            if ($row['event'] !== '') {
                $label .= ' · '.$row['event'];
            }
            $marks[] = [
                'id' => $row['id'],
                'name' => $label,
                'kind' => 'pay',
                'color' => '#059669',
                'dates' => [$row['date']],
            ];
        }

        return $marks;
    }

    protected function dutyMarks($events, $field, $label, $kind, $color)
    {
        $marks = [];
        if (! class_exists(\App\Event::class)) {
            return $marks;
        }
        foreach ($events as $event) {
            $dates = [];
            foreach (\App\Event::parseSetup(isset($event->{$field}) ? $event->{$field} : null) as $entry) {
                if (! empty($entry['date'])) {
                    $dates[] = $entry['date'];
                }
            }
            if (! count($dates)) {
                continue;
            }
            $marks[] = [
                'id' => (string) $event->id,
                'name' => $label.' · '.$event->name,
                'kind' => $kind,
                'color' => $color,
                'dates' => $dates,
            ];
        }

        return $marks;
    }

    protected function paymentDay($payment)
    {
        $notes = (string) (isset($payment->notes) ? $payment->notes : '');
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*\|/', $notes, $match)) {
            return $match[1];
        }
        if (! empty($payment->paid_at)) {
            return Carbon::parse($payment->paid_at)->toDateString();
        }

        return null;
    }

    protected function placeBar(Carbon $start, Carbon $end, array $days, array &$occupied)
    {
        if ($end->lt($days[0]) || $start->gt($days[6])) {
            return null;
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

        return [
            'col' => $col,
            'span' => $span,
            'lane' => $lane,
            'continues_before' => $start->lt($days[0]),
            'continues_after' => $end->gt($days[6]),
        ];
    }

    protected function dateSpans($value)
    {
        if (! class_exists(\App\Event::class)) {
            return [];
        }
        $dates = [];
        foreach (\App\Event::parseSetup($value) as $entry) {
            if (is_array($entry) && ! empty($entry['date'])) {
                $dates[] = $entry['date'];
            }
        }
        $dates = array_values(array_unique($dates));
        sort($dates);
        $spans = [];
        $start = null;
        $prev = null;
        foreach ($dates as $date) {
            $day = $this->day($date);
            if (! $day) {
                continue;
            }
            if ($start && $prev && $this->dayIndex($day, $prev) === 1) {
                $prev = $day;
                continue;
            }
            if ($start) {
                $spans[] = [$start, $prev];
            }
            $start = $day;
            $prev = $day;
        }
        if ($start) {
            $spans[] = [$start, $prev];
        }

        return $spans;
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
