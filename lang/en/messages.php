<?php

declare(strict_types=1);

/*
 * Only the model labels are translated so far; every other key falls back to Spanish.
 * The singular stays in lowercase because Filament places it inside sentences
 * («Delete booking»); the plural is a page title and starts with a capital.
 */
return [
    'model' => [
        'bookable_resource' => [
            'singular' => 'bookable space',
            'plural' => 'Bookable spaces',
        ],
        'booking' => [
            'singular' => 'booking',
            'plural' => 'Bookings',
        ],
    ],
];
