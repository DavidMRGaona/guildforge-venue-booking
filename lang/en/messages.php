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

    // The space booking an event proposal may ask for (core#581)
    'proposal_request' => [
        'label' => 'Space booking',
        'question' => 'Do you need to book a space?',
        'fields' => [
            'resource' => 'Space',
            'note' => 'Note for the booking',
            'note_help' => 'Optional. Read by whoever manages bookings.',
        ],
        'describe' => [
            'resource' => 'Booking of :resource',
            'with_note' => 'Booking of :resource (note: :note)',
            'unknown_resource' => 'Booking of a space that no longer exists',
        ],
        'warnings' => [
            'no_resource' => 'The space asked for no longer exists: choose another one or untick the request.',
            'resource_unavailable' => ':resource no longer takes bookings: choose another space or untick the request.',
            'multi_day' => 'The event runs from :date to :end_date and the booking of :resource covers a single day: whoever reviews it will have to book by hand.',
            'already_started' => 'The event started on :date at :start: whoever reviews it will have to book by hand, if it is still needed.',
            'outside_schedule' => 'The event hours (:date, :start to :end) do not fit the slots or opening hours of :resource: whoever reviews it will have to book by hand.',
            'slot_taken' => ':resource is already taken on :date between :start and :end: choose another space or other dates.',
        ],
        'refusals' => [
            'no_resource' => 'The space asked for no longer exists. Choose another one or untick the request.',
            'no_author' => 'The proposal has no author: untick the request and book by hand in the panel.',
            'multi_day' => 'The event lasts more than one day: untick the request and book by hand in the panel.',
            'already_booked' => 'The event already has a booking of :resource.',
            'slot_taken' => ':resource is already taken on :date between :start and :end. Choose another space or untick the request and book it by hand.',
            'outside_schedule' => 'The event hours (:date, :start to :end) do not fit the slots of :resource. Untick the request and book by hand in the panel.',
            'resource_unavailable' => ':resource no longer takes bookings. Choose another space or untick the request.',
            'already_started' => 'The event has already started: untick the request and, if still needed, book by hand in the panel.',
            'busy' => 'Another booking of :resource is being saved. Try again in a few seconds.',
        ],
    ],
];
