<?php

namespace App\Nbc;

class NbcJoinCatalog
{
    public static function titles()
    {
        return [
            'dr' => 'Dr.',
            'prof' => 'Prof.',
            'rev' => 'Rev.',
            'pastor' => 'Pastor',
            'mr' => 'Mr.',
            'mrs' => 'Mrs.',
            'miss' => 'Miss',
            'bro' => 'Bro.',
            'sr' => 'Sr.',
            'engr' => 'Engr.',
            'sr_engr' => 'Sr. Engr.',
            'other' => 'Other',
        ];
    }

    public static function education()
    {
        return [
            'elementary' => 'Elementary',
            'secondary' => 'Secondary',
            'university' => 'University',
            'masters' => 'Masters',
            'phd' => 'PhD',
        ];
    }

    public static function wings()
    {
        return [
            'men' => "Men's Department",
            'women' => "Women's Department",
            'youth' => 'Youth Department',
            'children' => "Children's Ministry",
            'ushering' => 'Ushering Department',
            'sunday_school' => 'Sunday School Department',
            'other' => 'Other',
        ];
    }

    public static function instruments()
    {
        return [
            'keyboard' => 'Keyboard',
            'acoustic_guitar' => 'Acoustic guitar',
            'bass_guitar' => 'Bass guitar',
            'lead_guitar' => 'Lead guitar',
            'drums' => 'Drums',
            'violin' => 'Violin',
            'saxophone' => 'Saxophone',
            'trumpet' => 'Trumpet',
            'flute' => 'Flute',
            'percussion' => 'Percussion',
            'other' => 'Other',
        ];
    }

    public static function vocals()
    {
        return [
            'lead' => 'Lead singer',
            'backup' => 'Backup singer',
        ];
    }

    public static function genres()
    {
        return [
            'praise' => 'Praise',
            'worship' => 'Worship',
        ];
    }

    public static function levels()
    {
        return [
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'professional' => 'Professional',
        ];
    }

    public static function partSummary(array $profile)
    {
        $bits = [];
        foreach (isset($profile['instruments']) ? $profile['instruments'] : [] as $row) {
            $label = isset($row['label']) ? $row['label'] : '';
            if ($label !== '') {
                $bits[] = $label;
            }
        }
        foreach (isset($profile['vocals']) ? $profile['vocals'] : [] as $row) {
            $label = isset($row['label']) ? $row['label'] : '';
            if ($label !== '') {
                $bits[] = $label;
            }
        }
        foreach (isset($profile['genres']) ? $profile['genres'] : [] as $row) {
            $label = isset($row['label']) ? $row['label'] : '';
            if ($label !== '') {
                $bits[] = $label;
            }
        }
        $text = implode(', ', $bits);
        if (function_exists('mb_substr') && mb_strlen($text) > 120) {
            return rtrim(mb_substr($text, 0, 117)).'…';
        }
        if (strlen($text) > 120) {
            return rtrim(substr($text, 0, 117)).'…';
        }

        return $text;
    }
}
