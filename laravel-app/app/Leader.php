<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Leader extends Model
{
    protected $fillable = [
        'name',
        'title',
        'description',
        'photo_url',
        'email',
        'phone',
        'country',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Public URL for the leader photo. */
    public function photoPublicUrl()
    {
        if (! $this->photo_url) {
            return null;
        }
        $path = trim((string) $this->photo_url);
        if (preg_match('#^(https?:)?//#', $path) || strpos($path, 'data:') === 0) {
            return $path;
        }
        if (strpos($path, '/') === 0) {
            return url(ltrim($path, '/'));
        }

        return url('public/'.ltrim($path, '/'));
    }

    public function countryFlag()
    {
        $map = self::countryFlags();

        return $map[$this->country] ?? '';
    }

    public static function countryFlags()
    {
        return [
            'Cameroon' => '🇨🇲',
            'Rwanda' => '🇷🇼',
            'Uganda' => '🇺🇬',
            'Kenya' => '🇰🇪',
            'Tanzania' => '🇹🇿',
            'Nigeria' => '🇳🇬',
            'Ghana' => '🇬🇭',
            'South Africa' => '🇿🇦',
            'Ethiopia' => '🇪🇹',
            'Egypt' => '🇪🇬',
            'Democratic Republic of the Congo' => '🇨🇩',
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
            'United Kingdom' => '🇬🇧',
            'France' => '🇫🇷',
            'Germany' => '🇩🇪',
            'Belgium' => '🇧🇪',
            'India' => '🇮🇳',
            'China' => '🇨🇳',
            'United Arab Emirates' => '🇦🇪',
            'Other' => '',
        ];
    }
}
