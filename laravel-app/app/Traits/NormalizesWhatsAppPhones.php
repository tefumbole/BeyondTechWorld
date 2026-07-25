<?php

namespace App\Traits;

use App\Support\WhatsAppPhone;

/**
 * Auto-sanitize phone attributes on save.
 * Define: protected $whatsappPhoneAttributes = ['phone', 'phone_number'];
 */
trait NormalizesWhatsAppPhones
{
    public static function bootNormalizesWhatsAppPhones()
    {
        static::saving(function ($model) {
            $attrs = property_exists($model, 'whatsappPhoneAttributes')
                ? (array) $model->whatsappPhoneAttributes
                : [];

            foreach ($attrs as $attr) {
                $value = $model->getAttribute($attr);
                if ($value === null || $value === '') {
                    continue;
                }
                $model->setAttribute($attr, WhatsAppPhone::sanitizeForStorage($value));
            }
        });
    }
}
