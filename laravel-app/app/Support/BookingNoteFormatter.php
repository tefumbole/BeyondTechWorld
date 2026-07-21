<?php

namespace App\Support;

class BookingNoteFormatter
{
    public static function forDisplay($note)
    {
        if ($note === null || trim((string) $note) === '') {
            return '';
        }

        $note = trim((string) $note);

        if (preg_match('/<[a-z][\s\S]*>/i', $note)) {
            return self::sanitizeHtml($note);
        }

        return nl2br(e($note), false);
    }

    public static function forPlainText($note)
    {
        if ($note === null || trim((string) $note) === '') {
            return '';
        }

        $note = trim((string) $note);
        $note = preg_replace('/<br\s*\/?>/i', "\n", $note);
        $note = preg_replace('/<\/p>/i', "\n", $note);
        $note = preg_replace('/<\/(li|h[1-6])>/i', "\n", $note);
        $note = strip_tags($note);

        return trim(preg_replace("/\n{3,}/", "\n\n", $note));
    }

    /** Sanitize rich-text note HTML before persisting (quotations, bookings, etc.). */
    public static function forStorage($note)
    {
        if ($note === null || trim((string) $note) === '') {
            return null;
        }

        return self::sanitizeHtml(trim((string) $note));
    }

    private static function sanitizeHtml($html)
    {
        return strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><span>');
    }
}
