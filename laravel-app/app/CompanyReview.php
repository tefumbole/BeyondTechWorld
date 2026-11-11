<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompanyReview extends Model
{
    protected $fillable = [
        'phone',
        'google_sub',
        'google_email',
        'reviewer_name',
        'google_photo',
        'country',
        'sector',
        'sector_other',
        'rating',
        'rating_value',
        'rating_quality',
        'rating_esthetics',
        'rating_professionalism',
        'rating_time',
        'comment',
        'photo_path',
        'signature_path',
        'invite_id',
        'published',
        'hidden_by_staff',
    ];

    protected $casts = [
        'rating' => 'integer',
        'rating_value' => 'integer',
        'rating_quality' => 'integer',
        'rating_esthetics' => 'integer',
        'rating_professionalism' => 'integer',
        'rating_time' => 'integer',
        'published' => 'boolean',
        'hidden_by_staff' => 'boolean',
    ];

    public static function sectors()
    {
        return [
            'equipment_rentals' => 'EQUIPMENT RENTALS',
            'supplies' => 'SUPPLIES',
            'it_services' => 'IT SERVICES',
            'software' => 'SOFTWARE',
            'others' => 'OTHERS',
        ];
    }

    public function sectorLabel()
    {
        $sectors = self::sectors();
        $key = (string) $this->sector;
        if ($key === 'others') {
            $other = trim((string) $this->sector_other);

            return $other !== '' ? 'OTHERS · '.$other : 'OTHERS';
        }

        return isset($sectors[$key]) ? $sectors[$key] : '';
    }

    public function meetsPublishRule()
    {
        return (int) $this->rating >= 6;
    }

    public static function publicSummary($reviews)
    {
        $count = 0;
        $sum = 0;
        $buckets = [];
        for ($n = 1; $n <= 10; $n++) {
            $buckets[$n] = 0;
        }
        foreach ($reviews as $review) {
            $score = (int) $review->rating;
            if ($score < 1 || $score > 10) {
                continue;
            }
            $count++;
            $sum += $score;
            $buckets[$score]++;
        }
        $bars = [];
        for ($n = 10; $n >= 1; $n--) {
            $bars[] = [
                'score' => $n,
                'count' => $buckets[$n],
                'percent' => $count > 0 ? (int) round(($buckets[$n] / $count) * 100) : 0,
            ];
        }

        return [
            'count' => $count,
            'average' => $count > 0 ? round($sum / $count, 1) : 0,
            'bars' => $bars,
        ];
    }

    public static function countryOptions()
    {
        $flags = Leader::countryFlags();
        $first = [];
        foreach (['Cameroon', 'United States'] as $name) {
            if (isset($flags[$name])) {
                $first[$name] = $flags[$name];
                unset($flags[$name]);
            }
        }

        return $first + $flags;
    }

    public static function canonicalCountry($input)
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        $aliases = [
            'usa' => 'United States',
            'us' => 'United States',
            'u.s.' => 'United States',
            'u.s.a.' => 'United States',
            'united states of america' => 'United States',
            'america' => 'United States',
            'cameroun' => 'Cameroon',
            'uk' => 'United Kingdom',
            'u.k.' => 'United Kingdom',
            'great britain' => 'United Kingdom',
        ];
        $key = strtolower($input);
        if (isset($aliases[$key])) {
            return $aliases[$key];
        }

        foreach (array_keys(Leader::countryFlags()) as $name) {
            if (strcasecmp($name, $input) === 0) {
                return $name;
            }
        }

        return null;
    }

    public function countryFlag()
    {
        $map = Leader::countryFlags();
        $name = trim((string) $this->country);

        return isset($map[$name]) ? $map[$name] : '';
    }

    public function countryLabel()
    {
        $name = trim((string) $this->country);
        $flag = $this->countryFlag();

        return trim(($flag !== '' ? $flag.' ' : '').$name);
    }

    public function portraitUrl()
    {
        if ($this->photo_path) {
            return self::publicUrl($this->photo_path);
        }

        return self::safeGooglePhoto($this->google_photo);
    }

    public function signatureUrl()
    {
        return $this->signature_path ? self::publicUrl($this->signature_path) : '';
    }

    public static function publicUrl($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return url('public/'.ltrim(str_replace('public/', '', $path), '/'));
    }

    public static function safeGooglePhoto($url)
    {
        $url = trim((string) $url);
        if ($url !== '' && preg_match('#^https://([a-z0-9.-]+\.)?googleusercontent\.com/#i', $url)) {
            return $url;
        }

        return '';
    }
}
