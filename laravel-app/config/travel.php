<?php

return [
    /*
    | Master switch. Leave false until sandbox tests pass.
    | A company also needs its own row in travel_settings.
    */
    'enabled' => env('TRAVEL_ENABLED', false),

    'sandbox' => env('TRAVEL_SANDBOX', true),

    'whatsapp_live' => env('TRAVEL_WHATSAPP_LIVE', false),

    'callback_secret' => env('TRAVEL_CALLBACK_SECRET', ''),

    /*
    | Used only when the request has no resolved company.
    | Public pages on this site use the resolved internal company.
    */
    'platform_tenant_id' => env('TRAVEL_PLATFORM_TENANT_ID'),

    'timezone' => 'Africa/Douala',

    'hold_minutes' => 5,

    'otp_minutes' => 10,

    'turnaround_minutes' => 60,

    'journey_minutes' => 540,

    /*
    | Terminals are bookable. Melong and Makenene are stops on the way, not destinations.
    | The return journey passes the same stop.
    */
    'stations' => [
        ['name' => 'Bamenda', 'kind' => 'terminal'],
        ['name' => 'Douala', 'kind' => 'terminal'],
        ['name' => 'Yaoundé', 'kind' => 'terminal'],
        ['name' => 'Buea', 'kind' => 'terminal', 'region' => 'South West'],
        ['name' => 'Limbe', 'kind' => 'terminal', 'region' => 'South West'],
        ['name' => 'Mutengene', 'kind' => 'terminal', 'region' => 'South West'],
        ['name' => 'Melong', 'kind' => 'stop'],
        ['name' => 'Makenene', 'kind' => 'stop'],
    ],

    'corridors' => [
        [
            'from' => 'Bamenda',
            'to' => ['Douala', 'Limbe', 'Buea', 'Mutengene'],
            'stop' => 'Melong',
        ],
        [
            'from' => 'Bamenda',
            'to' => ['Yaoundé'],
            'stop' => 'Makenene',
        ],
    ],

    'classes' => [
        'vip' => [
            'label' => 'VIP',
            'capacity' => 55,
            'fare' => 8000,
            'morning' => '09:00',
            'evening' => '17:00',
        ],
        'regular' => [
            'label' => 'Regular',
            'capacity' => 70,
            'fare' => 6000,
            'morning' => '10:00',
            'evening' => '18:00',
        ],
    ],
];
