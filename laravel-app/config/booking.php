<?php

return [
    /*
    | Product categories (by name, case-insensitive) treated as accommodation / housing.
    | Bookings where every line item matches accommodation use the student housing contract.
    */
    'accommodation_categories' => [
        'HOUSING',
        'APARTMENTS',
        'APARTMENT',
        'ACCOMMODATION',
        'HOUSING & APARTMENTS',
    ],

    /*
    | Calendar excludes these categories so long-term room rentals do not fill every day.
    */
    'calendar_excluded_categories' => [
        'HOUSING',
        'APARTMENTS',
        'APARTMENT',
        'ACCOMMODATION',
        'HOUSING & APARTMENTS',
    ],
];
