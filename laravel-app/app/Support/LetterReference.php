<?php

namespace App\Support;

use App\GeneralSetting;
use App\Letter;
use App\WaAnnouncement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared document references for Letters, Announcements, and WhatsApp.
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
     * Allocate the next shared serial (letters + announcements + WhatsApp) for the current year.
     */
    public static function next($source = 'document'): string
    {
        return DB::transaction(function () use ($source) {
            $prefix = self::prefix();
            $year = self::yearToken();
            $pattern = rtrim($prefix, '/').'/'.$year.'/';

            try {
                DB::table('general_settings')->lockForUpdate()->first();
            } catch (\Throwable $e) {
            }
            try {
                DB::table('wa_announcement_settings')->lockForUpdate()->first();
            } catch (\Throwable $e) {
            }

            $max = 0;
            foreach (Letter::query()->where('reference', 'like', $pattern.'%')->pluck('reference') as $ref) {
                $max = max($max, self::serialFromReference((string) $ref));
            }
            try {
                foreach (WaAnnouncement::query()->where('reference', 'like', $pattern.'%')->pluck('reference') as $ref) {
                    $max = max($max, self::serialFromReference((string) $ref));
                }
            } catch (\Throwable $e) {
            }
            if (Schema::hasTable('shared_message_serials')) {
                foreach (DB::table('shared_message_serials')->where('reference', 'like', $pattern.'%')->pluck('reference') as $ref) {
                    $max = max($max, self::serialFromReference((string) $ref));
                }
            }

            $next = $max + 1;
            $reference = self::format($prefix, $year, $next);

            if (Schema::hasTable('shared_message_serials')) {
                DB::table('shared_message_serials')->insert([
                    'reference' => $reference,
                    'source' => substr((string) $source, 0, 40),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $reference;
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

    public static function extractFromText($text): ?string
    {
        $text = (string) $text;
        if ($text === '') {
            return null;
        }
        $prefix = preg_quote(rtrim(self::prefix(), '/'), '#');
        if (preg_match('#'.$prefix.'/\d{2}/\d{1,7}#i', $text, $m)) {
            return $m[0];
        }

        return null;
    }

    /**
     * Stamp a shared letter serial on a WhatsApp body.
     * Subject / heading always stays first (chat preview).
     * Ref sits immediately above the italic system title (company name).
     * Reuses an existing letter-style serial instead of allocating a second one.
     */
    public static function applyToMessage($body, $source = 'whatsapp'): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", (string) $body);
        if (trim($body) === '') {
            return $body;
        }

        $ref = self::extractFromText($body);
        if (! $ref) {
            try {
                $ref = self::next($source);
            } catch (\Throwable $e) {
                \Log::warning('LetterReference WhatsApp serial failed: '.$e->getMessage());

                return rtrim($body);
            }
        }

        $body = self::stripRefLines($body);
        $body = self::stripTrailingSystemTitle($body);

        return self::insertBeforeFooter($body, self::label($ref));
    }

    /** Italic company line used as the WhatsApp system title. */
    public static function systemTitleLine(): string
    {
        $name = '';
        try {
            $name = trim((string) WhatsAppMessage::companyName());
        } catch (\Throwable $e) {
            $name = '';
        }
        if ($name === '') {
            $name = 'Beyond Enterprise';
        }

        return '_'.$name.'_';
    }

    /** Remove "Ref: PREFIX/yy/NNNNNNN" lines so they can be placed before the title. */
    public static function stripRefLines(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $prefix = preg_quote(rtrim(self::prefix(), '/'), '#');
        $stripped = preg_replace('#(?:^|\n)[ \t]*Ref:\s*'.$prefix.'/\d{2}/\d{1,7}[ \t]*#i', "\n", $body);

        return trim((string) $stripped, "\n");
    }

    /** Remove a trailing italic company title so we can re-attach it after Ref. */
    public static function stripTrailingSystemTitle(string $body): string
    {
        $body = rtrim(str_replace(["\r\n", "\r"], "\n", $body));
        $title = preg_quote(self::systemTitleLine(), '#');
        $stripped = preg_replace('#(?:\n+'.$title.')+\s*$#u', '', $body);
        $stripped = preg_replace('#(?:\n+_[^_\n]+_)\s*$#u', '', (string) $stripped);

        return rtrim((string) $stripped);
    }

    /**
     * Place Ref immediately above the italic system title at the bottom.
     * Never prepends Ref — the subject block must remain the first lines.
     */
    public static function insertBeforeFooter(string $body, string $label): string
    {
        $body = self::stripTrailingSystemTitle($body);
        $title = self::systemTitleLine();
        if ($label === '') {
            return $body === '' ? $title : $body."\n\n".$title;
        }

        if ($body === '') {
            return $label."\n".$title;
        }

        return $body."\n\n".$label."\n".$title;
    }
}
