<?php

namespace App\Services\Messaging;

/**
 * GSM 03.38 single-part is 160 septets. Concatenated parts are 153.
 * Unicode single-part is 70. Concatenated parts are 67.
 * Extended GSM characters count as two septets.
 */
class SmsSegmentEstimator
{
    public function estimate($text)
    {
        $text = (string) $text;
        if ($this->requiresUnicode($text)) {
            $length = mb_strlen($text, 'UTF-8');
            $segments = $this->parts($length, 70, 67);

            return ['encoding' => 'UCS2', 'characters' => $length, 'segments' => $segments];
        }
        $length = $this->gsmSeptets($text);

        return ['encoding' => 'GSM', 'characters' => $length, 'segments' => $this->parts($length, 160, 153)];
    }

    protected function parts($length, $single, $concat)
    {
        if ($length <= 0) {
            return 0;
        }
        if ($length <= $single) {
            return 1;
        }

        return (int) ceil($length / $concat);
    }

    protected function requiresUnicode($text)
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($chars)) {
            return true;
        }
        foreach ($chars as $char) {
            if (! $this->isGsm($char)) {
                return true;
            }
        }

        return false;
    }

    protected function gsmSeptets($text)
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $count = 0;
        foreach ($chars as $char) {
            $count += in_array($char, $this->extended(), true) ? 2 : 1;
        }

        return $count;
    }

    protected function isGsm($char)
    {
        return in_array($char, $this->basic(), true) || in_array($char, $this->extended(), true);
    }

    protected function basic()
    {
        $alphabet = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ ÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

        return preg_split('//u', $alphabet, -1, PREG_SPLIT_NO_EMPTY);
    }

    protected function extended()
    {
        return ['^', '{', '}', '\\', '[', '~', ']', '|', '€'];
    }
}
