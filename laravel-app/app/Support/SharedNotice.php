<?php

namespace App\Support;

/**
 * Generic WhatsApp notice. Any application can send Content SID
 * HXf57acecf17cbb538b7f46d192fc202cf with these variables. No company name is fixed in the text.
 *
 * {{1}} subject
 * {{2}} header
 * {{3}} recipient name
 * {{4}} body
 * {{5}} footer
 * {{6}} reference
 */
class SharedNotice
{
    const CONTENT_SID = 'HXf57acecf17cbb538b7f46d192fc202cf';

    public static function variables($subject, $header, $name, $body, $footer, $reference)
    {
        return [
            '1' => $subject,
            '2' => $header,
            '3' => $name,
            '4' => $body,
            '5' => $footer,
            '6' => $reference,
        ];
    }
}
