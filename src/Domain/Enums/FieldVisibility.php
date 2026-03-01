<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Enums;

enum FieldVisibility: string
{
    case Public = 'public';
    case Authenticated = 'authenticated';
    case Permission = 'permission';
    case AdminOnly = 'admin_only';

    public function label(): string
    {
        return match ($this) {
            self::Public => __('venue-bookings::messages.enums.field_visibility.public'),
            self::Authenticated => __('venue-bookings::messages.enums.field_visibility.authenticated'),
            self::Permission => __('venue-bookings::messages.enums.field_visibility.permission'),
            self::AdminOnly => __('venue-bookings::messages.enums.field_visibility.admin_only'),
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Authenticated => 1,
            self::Permission => 2,
            self::AdminOnly => 3,
        };
    }

    public function isVisibleToEveryone(): bool
    {
        return $this === self::Public;
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
