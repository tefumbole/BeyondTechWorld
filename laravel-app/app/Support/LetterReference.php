<?php

namespace App\Support;

use App\GeneralSetting;
use App\Letter;
use App\WaAnnouncement;
use Illuminate\Support\Facades\DB;

/**
 * Shared document references for Letters and Announcements.
 * Format: {letter_serial_no}/{yy}/{NNNNNNN} e.g. BCL/L-/26/0000005
 */
class LetterReference
{
    public static function prefix(): string
    {
        $setting = GeneralSetting::first();
        $prefix = trim((string) optional($setting)->letter_serial_no);

        return $prefix !== '' ? $prefix : 'BCL/L-';
    }

    public static function yearToken($date = null): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('y');
        }

        return date('y');
    }

    /**
     * Allocate the next shared serial (letters + announcements) for the current year.
     */
    public static function next(): string
    {
        return DB::transaction(function () {
            $prefix = self::prefix();
            $year = self::yearToken();
            $pattern = $prefix.'/'.$year.'/';

            // Lock announcement settings row lightly so concurrent creates serialize.
            try {
                DB::table('wa_announcement_settings')->lockForUpdate()->first();
            } catch (\Throwable $e) {
                // table may not exist in odd environments — continue
            }

            $max = 0;
            foreach (Letter::query()->where('reference', 'like', $pattern.'%')->pluck('reference') as $ref) {
                $max = max($max, self::serialFromReference((string) $ref));
            }
            foreach (WaAnnouncement::query()->where('reference', 'like', $pattern.'%')->pluck('reference') as $ref) {
                $max = max($max, self::serialFromReference((string) $ref));
            }

            $next = $max + 1;

            return self::format($prefix, $year, $next);
        });
    }

    public static function format(string $prefix, string $year, int $serial): string
    {
        return rtrim($prefix, '/').'/'.$year.'/'.str_pad((string) $serial, 7, '0', STR_PAD_LEFT);
    }

    public static function serialFromReference(string $reference): int
    {
        if (preg_match('#/(\d+)\s*$#', $reference, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /** Display line matching letters: "Ref: BCL/L-/26/0000005" */
    public static function label(?string $reference): string
    {
        $reference = trim((string) $reference);
        if ($reference === '') {
            return '';
        }

        return 'Ref: '.$reference;
    }
}
