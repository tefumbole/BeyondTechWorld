<?php

return [
    /*
    | Tenant SMS. live must stay false until a documented provider adapter exists.
    | driver=fake is for tests. disabled refuses every send.
    */
    'sms' => [
        'live' => false,
        'driver' => env('MESSAGING_SMS_DRIVER', 'disabled'),
        'webhook_secret' => env('MESSAGING_SMS_WEBHOOK_SECRET', ''),
        'fallback_on_permanent_recipient' => true,
        'fallback_on_temporary' => false,
        'allow_test_credits' => env('MESSAGING_SMS_TEST_CREDITS', true),
        /*
        | Live Infobip sends stay off until an operator sets the driver.
        | The ceiling is a segment cap for that validation, not a price.
        */
        'validation_segment_ceiling' => 20,
        'infobip' => [
            'base_url' => rtrim((string) env('INFOBIP_BASE_URL', ''), '/'),
            'api_key' => env('INFOBIP_API_KEY', ''),
            'sender' => env('INFOBIP_SENDER', ''),
            'entity_id' => env('INFOBIP_ENTITY_ID', ''),
            'application_id' => env('INFOBIP_APPLICATION_ID', ''),
            'delivery_url' => env('INFOBIP_DELIVERY_URL', ''),
            'connect_timeout' => 5,
            'timeout' => 15,
        ],
    ],
];
