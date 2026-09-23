<?php

return [
    /*
     * Editable equipment suggestions. Each query is searched in the ERP catalogue.
     * A suggestion is shown only when that product exists, has a daily rate, and has quantity.
     */
    'profiles' => [
        'sound' => [
            ['max_guests' => 150, 'queries' => ['speaker', 'microphone']],
            ['max_guests' => 100000, 'queries' => ['speaker', 'subwoofer', 'microphone', 'mixer']],
        ],
        'lighting' => [
            ['max_guests' => 100000, 'queries' => ['moving head', 'par light']],
        ],
        'led' => [
            ['max_guests' => 100000, 'queries' => ['led screen', 'led']],
        ],
    ],
];
