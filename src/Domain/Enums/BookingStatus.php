<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('venue-bookings::messages.enums.booking_status.pending'),
            self::Confirmed => __('venue-bookings::messages.enums.booking_status.confirmed'),
            self::Completed => __('venue-bookings::messages.enums.booking_status.completed'),
            self::Cancelled => __('venue-bookings::messages.enums.booking_status.cancelled'),
            self::NoShow => __('venue-bookings::messages.enums.booking_status.no_show'),
            self::Rejected => __('venue-bookings::messages.enums.booking_status.rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
            self::NoShow => 'danger',
            self::Rejected => 'danger',
        };
    }

    public function calendarCssColor(): string
    {
        return match ($this) {
            self::Pending => 'var(--color-warning)',
            self::Confirmed => 'var(--color-success)',
            self::Completed => 'var(--color-secondary)',
            self::Cancelled, self::NoShow, self::Rejected => 'var(--color-error)',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow, self::Rejected], true);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Pending => in_array($newStatus, [self::Confirmed, self::Cancelled, self::Rejected], true),
            self::Confirmed => in_array($newStatus, [self::Completed, self::Cancelled, self::NoShow], true),
            self::Completed, self::Cancelled, self::NoShow, self::Rejected => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case): string => $case->label(), self::cases()),
        );
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
