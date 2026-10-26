<?php

declare(strict_types=1);

// Default settings, the only place they are written. The core loads them with the values saved
// in the panel on top (settings table, key: module_settings:venue-bookings), and
// BookingSettingsReader::get() falls back to them; it refuses a key missing here.

return [
    'approval_mode' => 'require_approval',
    'max_active_bookings_per_user' => 3,
    'min_advance_minutes' => 60,
    'max_future_days' => 30,
    // Confirmed bookings become completed this many hours after they end
    'auto_complete_enabled' => true,
    'auto_complete_after_hours' => 24,
    'time_block_presets' => [],
    'predefined_fields' => [
        'activity_name' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
        'num_participants' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
        'contact_phone' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
        'notes' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
    ],
    'custom_fields' => [],
    'notifications' => [
        'notify_on_booking_created' => true,
        'notify_on_booking_received' => true,
        'notify_on_booking_confirmed' => true,
        'notify_on_booking_cancelled' => true,
        'notify_on_booking_rejected' => true,
        'notify_on_booking_reminder' => true,
        'reminder_hours_before' => 24,
    ],
    // Links to other modules' records on the booking form
    'associations' => [
        'game_tables_enabled' => false,
        'game_tables_visibility' => 'authenticated',
        'game_tables_required' => false,
        'campaigns_enabled' => false,
        'campaigns_visibility' => 'authenticated',
        'campaigns_required' => false,
        'tournaments_enabled' => false,
        'tournaments_visibility' => 'authenticated',
        'tournaments_required' => false,
    ],
    // Venue timezone for booking dates and times; null uses the site's display
    // timezone
    'timezone' => null,
];
