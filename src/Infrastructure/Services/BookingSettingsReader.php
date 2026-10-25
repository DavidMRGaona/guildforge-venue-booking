<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use DateTimeZone;
use Illuminate\Support\Arr;
use LogicException;
use Modules\VenueBookings\Domain\Enums\ApprovalMode;

/**
 * The module settings. config/settings.php is the only place their defaults are written: the
 * core loads it with the values saved in the panel on top, and get() falls back to it when the
 * settings were not loaded (a provider not registered, as in tests).
 */
final readonly class BookingSettingsReader
{
    /**
     * The setting at $key (dot notation): the loaded value, else its default.
     *
     * @throws LogicException when config/settings.php does not declare $key
     */
    public function get(string $key): mixed
    {
        $defaults = $this->defaults();

        if (! Arr::has($defaults, $key)) {
            throw new LogicException("The venue-bookings setting \"{$key}\" is not declared in config/settings.php.");
        }

        return config("modules.settings.venue-bookings.{$key}") ?? Arr::get($defaults, $key);
    }

    public function getApprovalMode(): ApprovalMode
    {
        return ApprovalMode::from((string) $this->get('approval_mode'));
    }

    public function requiresApproval(): bool
    {
        return $this->getApprovalMode()->requiresApproval();
    }

    public function getMaxActiveBookings(): int
    {
        return (int) $this->get('max_active_bookings_per_user');
    }

    public function getMinAdvanceMinutes(): int
    {
        return (int) $this->get('min_advance_minutes');
    }

    public function getMaxFutureDays(): int
    {
        return (int) $this->get('max_future_days');
    }

    public function isAutoCompleteEnabled(): bool
    {
        return (bool) $this->get('auto_complete_enabled');
    }

    /**
     * Hours after a booking ends before it is completed automatically, kept within 0-720
     * so a value stored by hand can never complete a booking before it ends.
     */
    public function getAutoCompleteAfterHours(): int
    {
        return max(0, min(720, (int) $this->get('auto_complete_after_hours')));
    }

    public function getReminderHoursBefore(): int
    {
        return (int) $this->get('notifications.reminder_hours_before');
    }

    public function isNotifyOnBookingCreatedEnabled(): bool
    {
        return (bool) $this->get('notifications.notify_on_booking_created');
    }

    public function isNotifyOnBookingReceivedEnabled(): bool
    {
        return (bool) $this->get('notifications.notify_on_booking_received');
    }

    public function isNotifyOnBookingConfirmedEnabled(): bool
    {
        return (bool) $this->get('notifications.notify_on_booking_confirmed');
    }

    public function isNotifyOnBookingCancelledEnabled(): bool
    {
        return (bool) $this->get('notifications.notify_on_booking_cancelled');
    }

    public function isNotifyOnBookingRejectedEnabled(): bool
    {
        return (bool) $this->get('notifications.notify_on_booking_rejected');
    }

    /**
     * @return array<array{label: string, open_time: string, close_time: string}>
     */
    public function getTimeBlockPresets(): array
    {
        return (array) $this->get('time_block_presets');
    }

    public function getTimezone(): string
    {
        $tz = $this->get('timezone');

        return is_string($tz) && $tz !== '' ? $tz : config('app.timezone', 'UTC');
    }

    /**
     * The venue timezone, in which booking dates and times are read.
     */
    public function getDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->getTimezone());
    }

    public function isReminderEnabled(): bool
    {
        return (bool) $this->get('notifications.notify_on_booking_reminder');
    }

    /**
     * config/settings.php, read once per process.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        static $defaults = null;

        if ($defaults === null) {
            $loaded = require dirname(__DIR__, 3).'/config/settings.php';
            $defaults = is_array($loaded) ? $loaded : [];
        }

        return $defaults;
    }
}
