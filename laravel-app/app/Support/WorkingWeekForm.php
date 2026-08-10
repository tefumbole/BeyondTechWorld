<?php

namespace App\Support;

use App\WorkingWeek;
use Illuminate\Validation\ValidationException;

/**
 * Shared helpers for Working Week forms on apply / offer portal / timesheets.
 */
class WorkingWeekForm
{
    public static function defaultData()
    {
        $data = ['lunch_break_minutes' => 60];
        foreach (WorkingWeek::days() as $day) {
            $weekday = ! in_array($day, ['saturday', 'sunday'], true);
            $data[$day] = $weekday;
            $data[$day.'_start'] = '08:00';
            $data[$day.'_end'] = '17:00';
        }

        return $data;
    }

    public static function fromArray($raw)
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        $data = self::defaultData();
        if (isset($raw['lunch_break_minutes'])) {
            $data['lunch_break_minutes'] = (int) $raw['lunch_break_minutes'];
        }
        foreach (WorkingWeek::days() as $day) {
            if (array_key_exists($day, $raw)) {
                $data[$day] = filter_var($raw[$day], FILTER_VALIDATE_BOOLEAN)
                    || $raw[$day] === 1
                    || $raw[$day] === '1';
            }
            if (! empty($raw[$day.'_start'])) {
                $data[$day.'_start'] = substr((string) $raw[$day.'_start'], 0, 5);
            }
            if (! empty($raw[$day.'_end'])) {
                $data[$day.'_end'] = substr((string) $raw[$day.'_end'], 0, 5);
            }
        }

        return $data;
    }

    public static function fromRequest($request)
    {
        $data = ['lunch_break_minutes' => (int) $request->input('lunch_break_minutes', 60)];
        foreach (WorkingWeek::days() as $day) {
            $data[$day] = $request->boolean($day) || $request->input($day) === '1';
            $data[$day.'_start'] = substr((string) $request->input($day.'_start', '08:00'), 0, 5);
            $data[$day.'_end'] = substr((string) $request->input($day.'_end', '17:00'), 0, 5);
        }

        return $data;
    }

    public static function validationRules($prefix = '')
    {
        $p = $prefix ? rtrim($prefix, '.').'.' : '';
        $rules = [
            $p.'lunch_break_minutes' => 'nullable|integer|min:0|max:180',
        ];
        foreach (WorkingWeek::days() as $day) {
            $rules[$p.$day] = 'nullable';
            $rules[$p.$day.'_start'] = 'nullable|date_format:H:i';
            $rules[$p.$day.'_end'] = 'nullable|date_format:H:i';
        }

        return $rules;
    }

    public static function assertValid(array $data, $field = 'working_week')
    {
        $active = 0;
        foreach (WorkingWeek::days() as $day) {
            if (! empty($data[$day])) {
                $active++;
                $start = $data[$day.'_start'] ?? '';
                $end = $data[$day.'_end'] ?? '';
                if ($start === '' || $end === '') {
                    throw ValidationException::withMessages([
                        $field => ['Set start and end times for each working day.'],
                    ]);
                }
            }
        }
        if ($active < 1) {
            throw ValidationException::withMessages([
                $field => ['Select at least one working day for your Working Week.'],
            ]);
        }
    }

    public static function toJson(array $data)
    {
        return json_encode(self::fromArray($data));
    }

    public static function summary(array $data)
    {
        $data = self::fromArray($data);
        $lunch = (int) ($data['lunch_break_minutes'] ?? 60);
        $workingDays = 0;
        $expected = 0.0;
        $dayHours = [];
        foreach (WorkingWeek::days() as $day) {
            $h = 0.0;
            if (! empty($data[$day])) {
                $workingDays++;
                $h = self::hoursBetween($data[$day.'_start'] ?? '08:00', $data[$day.'_end'] ?? '17:00', $lunch);
                $expected += $h;
            }
            $dayHours[$day] = $h;
        }

        return [
            'working_days' => $workingDays,
            'expected' => round($expected, 2),
            'day_hours' => $dayHours,
            'lunch' => $lunch,
        ];
    }

    public static function hoursBetween($start, $end, $lunchMinutes = 0)
    {
        try {
            $s = \Carbon\Carbon::createFromFormat('H:i', substr((string) $start, 0, 5));
            $e = \Carbon\Carbon::createFromFormat('H:i', substr((string) $end, 0, 5));
        } catch (\Throwable $ex) {
            return 0.0;
        }
        $mins = $e->diffInMinutes($s, false);
        if ($mins < 0) {
            $mins += 24 * 60;
        }
        $mins -= (int) $lunchMinutes;
        if ($mins < 0) {
            $mins = 0;
        }

        return round($mins / 60, 2);
    }
}
