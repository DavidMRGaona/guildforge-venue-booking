<?php

return array (
  'approval_mode' => 'require_approval',
  'max_active_bookings_per_user' => '19',
  'min_advance_minutes' => '60',
  'max_future_days' => '15',
  'time_block_presets' => 
  array (
    0 => 
    array (
      'label' => 'Mañana',
      'open_time' => '10:00',
      'close_time' => '14:00',
    ),
    1 => 
    array (
      'label' => 'Tarde',
      'open_time' => '14:00',
      'close_time' => '20:00',
    ),
    2 => 
    array (
      'label' => 'Noche',
      'open_time' => '20:00',
      'close_time' => '10:00',
    ),
  ),
  'predefined_fields' => 
  array (
    'activity_name' => 
    array (
      'enabled' => true,
      'required' => true,
      'visibility' => 'public',
    ),
    'num_participants' => 
    array (
      'enabled' => true,
      'required' => true,
      'visibility' => 'public',
    ),
    'contact_phone' => 
    array (
      'enabled' => true,
      'required' => false,
      'visibility' => 'admin_only',
    ),
    'notes' => 
    array (
      'enabled' => true,
      'required' => true,
      'visibility' => 'authenticated',
    ),
  ),
  'custom_fields' => 
  array (
    0 => 
    array (
      'key' => 'num-socios',
      'label' => 'Nº Socios',
      'type' => 'number',
      'required' => true,
      'visibility' => 'admin_only',
    ),
  ),
  'notifications' => 
  array (
    'notify_on_booking_created' => true,
    'notify_on_booking_confirmed' => true,
    'notify_on_booking_cancelled' => true,
    'notify_on_booking_rejected' => true,
    'notify_on_booking_reminder' => true,
    'reminder_hours_before' => 24,
  ),
  'associations' => 
  array (
    'game_tables_enabled' => true,
    'game_tables_visibility' => 'public',
    'campaigns_enabled' => true,
    'campaigns_visibility' => 'public',
  ),
);
