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
        'service' => env('WHATSAPP_SERVICE', 'WASENDER'),
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
    ],

];
