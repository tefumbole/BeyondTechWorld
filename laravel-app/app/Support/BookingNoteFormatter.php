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

    /**
     * Default editable note for new quotations (formerly the fixed "Quotation agreement" block).
     * Staff can edit or clear this in the create/edit editor before sending.
     */
    public static function defaultQuotationNote($companyName = null)
    {
        $company = trim((string) ($companyName ?: 'Beyond Tech World'));
        if ($company === '') {
            $company = 'Beyond Tech World';
        }
        $company = e($company);

        return self::sanitizeHtml(
            '<p><strong>Please read carefully before approving or rejecting:</strong></p>'
            .'<ol>'
            .'<li><strong>This document is a quotation, not a receipt or invoice.</strong> '
            .'It is an offer of goods/services and pricing for your consideration only. '
            .'No payment obligation arises until a sale or booking is confirmed after your approval.</li>'
            .'<li><strong>Suppliers / fulfilment will be arranged upon cleared payments.</strong> '
            .'Procurement, reservation, or delivery of items proceeds only after payment has been received '
            .'and cleared as agreed with '.$company.'.</li>'
            .'<li><strong>You reserve the right to request modifications.</strong> '
            .'You may request changes to quantities, items, or terms. '
            .'Revised quotations may be issued for your review before final acceptance.</li>'
            .'<li>By signing and approving, you confirm that you have reviewed the quoted items and totals, '
            .'and you authorise '.$company.' to proceed toward order processing subject to payment and availability.</li>'
            .'</ol>'
        );
    }

    private static function sanitizeHtml($html)
    {
        return strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><span>');
    }
}
