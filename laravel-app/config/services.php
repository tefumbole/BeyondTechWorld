<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'whatsapp' => [
        // TWILIO = all texts/OTP/announcements via beyond_notice Content Template.
        // WASENDER = free-form WasenderAPI (comment/clear Wasender keys when on Twilio-only).
        'service' => env('WHATSAPP_SERVICE', 'TWILIO'),
        'enabled' => env('MESSAGING_WHATSAPP_ENABLED', true),
        'wasender_api_key' => env('WASENDER_API_KEY'),
        'wasender_session_id' => env('WASENDER_SESSION_ID'),
        'wasender_base_url' => env('WASENDER_BASE_URL', env('WASENDER_API_URL', 'https://wasenderapi.com/api')),
        'min_send_interval_ms' => (int) env(
            'WASENDER_MIN_SEND_INTERVAL_MS',
            (int) env('WHATSAPP_SEND_INTERVAL', 6) * 1000
        ),
        'text_to_document_delay_ms' => (int) env('WASENDER_TEXT_TO_DOCUMENT_DELAY_MS', 6000),
        'company_name' => env('COMPANY_NAME'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '237'),
        'ultramsg_instance' => env('ULTRAMSG_INSTANCE'),
        'ultramsg_token' => env('ULTRAMSG_TOKEN'),
        'twilio_sid' => env('TWILIO_SID', env('ACCOUNT_SID')),
        'twilio_token' => env('TWILIO_AUTH_TOKEN', env('AUTH_TOKEN')),
        'twilio_whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        // beyond_notice — 5 vars: headline, name, message, reference, extra
        'content_sid_admission' => env(
            'TWILIO_WHATSAPP_CONTENT_SID_ADMISSION',
            'HX47150e179fdbab79738d060fb0ac6415'
        ),
        'content_sid_otp' => env('TWILIO_WHATSAPP_CONTENT_SID_OTP'),
        'content_sid_status' => env(
            'TWILIO_WHATSAPP_CONTENT_SID_STATUS',
            'HX47150e179fdbab79738d060fb0ac6415'
        ),
        'twilio_fallback_wasender' => env('WHATSAPP_TWILIO_FALLBACK_WASENDER', false),
    ],

    'sms' => [
        'enabled' => env('MESSAGING_SMS_ENABLED', true),
        'gateway' => env('SMS_GATEWAY', 'twilio'),
        'account_sid' => env('ACCOUNT_SID', env('TWILIO_SID')),
        'auth_token' => env('AUTH_TOKEN', env('TWILIO_AUTH_TOKEN')),
        'twilio_number' => env('TWILIO_NUMBER', env('Twilio_Number')),
        'clickatell_api_key' => env('CLICKATELL_API_KEY'),
    ],

];
