<?php

namespace App\Support;

/**
 * Generic WhatsApp notice. Any application can send Content SID
 * HX84b76dc8478ae8d400da0287f805683e with these variables. No company name is fixed in the text.
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
    const CONTENT_SID = 'HX84b76dc8478ae8d400da0287f805683e';

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
