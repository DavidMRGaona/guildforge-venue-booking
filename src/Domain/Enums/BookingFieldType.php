<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Enums;

enum BookingFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Select = 'select';
    case Toggle = 'toggle';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('venue-bookings::messages.enums.field_type.text'),
            self::Textarea => __('venue-bookings::messages.enums.field_type.textarea'),
            self::Number => __('venue-bookings::messages.enums.field_type.number'),
            self::Select => __('venue-bookings::messages.enums.field_type.select'),
            self::Toggle => __('venue-bookings::messages.enums.field_type.toggle'),
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
}
