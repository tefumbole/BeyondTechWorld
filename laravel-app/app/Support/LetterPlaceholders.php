<?php

namespace App\Support;

class LetterPlaceholders
{
    /**
     * Build replacement map for letter / PDF personalization.
     * Supports standard tokens plus internship acceptance tokens.
     *
     * @param  object|null  $recipient
     * @return array<string, string>
     */
    public static function map($recipient): array
    {
        $r = $recipient ?: (object) [];

        $get = function ($key) use ($r) {
            if (is_array($r)) {
                return (string) ($r[$key] ?? '');
            }

            return (string) ($r->{$key} ?? '');
        };

        $map = [
            '[name]' => $get('name'),
            '[phone_number]' => $get('phone_number'),
            '[email]' => $get('email'),
            '[address]' => $get('address'),
            '[school]' => $get('school'),
            '[system_name]' => $get('system_name'),
            '[program]' => $get('program'),
            '[supervisors]' => $get('supervisors'),
            '[start_date]' => $get('start_date'),
            '[duration]' => $get('duration'),
            '[password]' => $get('password'),
            '[username]' => $get('username') !== '' ? $get('username') : $get('phone_number'),
        ];

        for ($i = 1; $i <= 10; $i++) {
            $val = $get('column'.$i);
            $map['[Column'.$i.']'] = $val;
            $map['[column'.$i.']'] = $val;
        }

        return $map;
    }

    /**
     * @param  string|null  $text
     * @param  object|null  $recipient
     */
    public static function replace($text, $recipient): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        return strtr((string) $text, self::map($recipient));
    }
}
