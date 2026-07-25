<?php

namespace App;

use App\Traits\NormalizesWhatsAppPhones;
use Illuminate\Database\Eloquent\Model;

class Leader extends Model
{
    use NormalizesWhatsAppPhones;

    protected $whatsappPhoneAttributes = ['phone'];

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
        $name = trim((string) $this->country);
        if ($name === '') {
            return '';
        }

        $map = self::countryFlags();
        if (isset($map[$name])) {
            return $map[$name];
        }

        foreach ($map as $key => $flag) {
            if (strcasecmp($key, $name) === 0) {
                return $flag;
            }
        }

        return '';
    }

    public function countryLabel()
    {
        $name = trim((string) $this->country);
        if ($name === '') {
            return '';
        }
        $flag = $this->countryFlag();

        return trim(($flag ? $flag.' ' : '').$name);
    }

    /** name => flag emoji (empty string allowed). */
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
            'Morocco' => '🇲🇦',
            'Algeria' => '🇩🇿',
            'Tunisia' => '🇹🇳',
            'Senegal' => '🇸🇳',
            "Côte d'Ivoire" => '🇨🇮',
            'Democratic Republic of the Congo' => '🇨🇩',
            'Republic of the Congo' => '🇨🇬',
            'Gabon' => '🇬🇦',
            'Chad' => '🇹🇩',
            'Central African Republic' => '🇨🇫',
            'Equatorial Guinea' => '🇬🇶',
            'Angola' => '🇦🇴',
            'Zambia' => '🇿🇲',
            'Zimbabwe' => '🇿🇼',
            'Mozambique' => '🇲🇿',
            'Botswana' => '🇧🇼',
            'Namibia' => '🇳🇦',
            'Burundi' => '🇧🇮',
            'Malawi' => '🇲🇼',
            'Mali' => '🇲🇱',
            'Burkina Faso' => '🇧🇫',
            'Niger' => '🇳🇪',
            'Guinea' => '🇬🇳',
            'Benin' => '🇧🇯',
            'Togo' => '🇹🇬',
            'Sierra Leone' => '🇸🇱',
            'Liberia' => '🇱🇷',
            'Sudan' => '🇸🇩',
            'South Sudan' => '🇸🇸',
            'Somalia' => '🇸🇴',
            'Libya' => '🇱🇾',
            'United States' => '🇺🇸',
            'Canada' => '🇨🇦',
            'United Kingdom' => '🇬🇧',
            'Ireland' => '🇮🇪',
            'France' => '🇫🇷',
            'Germany' => '🇩🇪',
            'Spain' => '🇪🇸',
            'Portugal' => '🇵🇹',
            'Italy' => '🇮🇹',
            'Netherlands' => '🇳🇱',
            'Belgium' => '🇧🇪',
            'Switzerland' => '🇨🇭',
            'Sweden' => '🇸🇪',
            'Norway' => '🇳🇴',
            'Denmark' => '🇩🇰',
            'Finland' => '🇫🇮',
            'Poland' => '🇵🇱',
            'Russia' => '🇷🇺',
            'Turkey' => '🇹🇷',
            'United Arab Emirates' => '🇦🇪',
            'Saudi Arabia' => '🇸🇦',
            'Qatar' => '🇶🇦',
            'India' => '🇮🇳',
            'Pakistan' => '🇵🇰',
            'China' => '🇨🇳',
            'Japan' => '🇯🇵',
            'South Korea' => '🇰🇷',
            'Indonesia' => '🇮🇩',
            'Malaysia' => '🇲🇾',
            'Singapore' => '🇸🇬',
            'Philippines' => '🇵🇭',
            'Australia' => '🇦🇺',
            'New Zealand' => '🇳🇿',
            'Brazil' => '🇧🇷',
            'Argentina' => '🇦🇷',
            'Mexico' => '🇲🇽',
        ];
    }
}
