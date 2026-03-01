<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Modules\VenueBookings\Domain\Enums\ApprovalMode;

final readonly class BookingSettingsReader
{
    public function getApprovalMode(): ApprovalMode
    {
        $value = (string) (config('modules.settings.venue-bookings.approval_mode')
            ?? config('venue-bookings.approval_mode', 'require_approval'));

        return ApprovalMode::from($value);
    }

    public function requiresApproval(): bool
    {
        return $this->getApprovalMode()->requiresApproval();
    }

    public function getMaxActiveBookings(): int
    {
        return (int) (config('modules.settings.venue-bookings.max_active_bookings_per_user')
            ?? config('venue-bookings.max_active_bookings_per_user', 3));
    }

    public function getMinAdvanceMinutes(): int
    {
        return (int) (config('modules.settings.venue-bookings.min_advance_minutes')
            ?? config('venue-bookings.min_advance_minutes', 60));
    }

    public function getMaxFutureDays(): int
    {
        return (int) (config('modules.settings.venue-bookings.max_future_days')
            ?? config('venue-bookings.max_future_days', 30));
    }

    public function getReminderHoursBefore(): int
    {
        return (int) (config('modules.settings.venue-bookings.notifications.reminder_hours_before')
            ?? config('venue-bookings.notifications.reminder_hours_before', 24));
    }

    public function isNotifyOnBookingCreatedEnabled(): bool
    {
        return (bool) (config('modules.settings.venue-bookings.notifications.notify_on_booking_created')
            ?? config('venue-bookings.notifications.notify_on_booking_created', true));
    }

    public function isNotifyOnBookingConfirmedEnabled(): bool
    {
        return (bool) (config('modules.settings.venue-bookings.notifications.notify_on_booking_confirmed')
            ?? config('venue-bookings.notifications.notify_on_booking_confirmed', true));
    }

    public function isNotifyOnBookingCancelledEnabled(): bool
    {
        return (bool) (config('modules.settings.venue-bookings.notifications.notify_on_booking_cancelled')
            ?? config('venue-bookings.notifications.notify_on_booking_cancelled', true));
    }

    public function isNotifyOnBookingRejectedEnabled(): bool
    {
        return (bool) (config('modules.settings.venue-bookings.notifications.notify_on_booking_rejected')
            ?? config('venue-bookings.notifications.notify_on_booking_rejected', true));
    }

    /**
     * @return array<array{label: string, open_time: string, close_time: string}>
     */
    public function getTimeBlockPresets(): array
    {
        /** @var array<array{label: string, open_time: string, close_time: string}> */
        return config('modules.settings.venue-bookings.time_block_presets')
            ?? config('venue-bookings.time_block_presets', []);
    }

    public function isReminderEnabled(): bool
    {
        return (bool) (config('modules.settings.venue-bookings.notifications.reminder_enabled')
            ?? config('venue-bookings.notifications.reminder_enabled', true));
    }
}
