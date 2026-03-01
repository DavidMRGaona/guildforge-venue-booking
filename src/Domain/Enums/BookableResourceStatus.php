<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Enums;

enum BookableResourceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('venue-bookings::messages.enums.resource_status.active'),
            self::Inactive => __('venue-bookings::messages.enums.resource_status.inactive'),
            self::Maintenance => __('venue-bookings::messages.enums.resource_status.maintenance'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
            self::Maintenance => 'warning',
        };
    }

    public function isBookable(): bool
    {
        return $this === self::Active;
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
}
