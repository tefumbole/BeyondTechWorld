<?php

namespace App\Support;

use Carbon\Carbon;

class PaidInternship
{
    public static function label()
    {
        return 'Paid internship';
    }

    public static function minDays()
    {
        return 30;
    }

    public static function maxDays()
    {
        return 180;
    }

    public static function closesAt()
    {
        return Carbon::create(2026, 11, 30, 23, 59, 59, config('app.timezone') ?: 'UTC');
    }

    public static function isOpen()
    {
        return Carbon::now()->lte(self::closesAt());
    }

    /**
     * Longer stays cost less per day. 30 days is 15,000. 60 days is 25,000.
     * Each step after that adds less than the one before, through 180 days.
     *
     * @return array<int,int>
     */
    public static function anchors()
    {
        return [
            30 => 15000,
            45 => 20000,
            60 => 25000,
            90 => 30000,
            120 => 34000,
            150 => 37000,
            180 => 40000,
        ];
    }

    public static function price($days)
    {
        $days = (int) $days;
        $anchors = self::anchors();
        $keys = array_keys($anchors);
        $first = $keys[0];
        $last = $keys[count($keys) - 1];
        if ($days <= $first) {
            return $anchors[$first];
        }
        if ($days >= $last) {
            return $anchors[$last];
        }
        for ($i = 0; $i < count($keys) - 1; $i++) {
            $from = $keys[$i];
            $to = $keys[$i + 1];
            if ($days < $from || $days > $to) {
                continue;
            }
            $span = $to - $from;
            $ratio = ($days - $from) / $span;

            return (int) round($anchors[$from] + (($anchors[$to] - $anchors[$from]) * $ratio));
        }

        return $anchors[$last];
    }

    public static function money($amount)
    {
        return number_format((int) $amount, 0, '.', ' ').' XAF';
    }
}
