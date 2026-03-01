<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Enums;

enum SchedulingMode: string
{
    case TimeSlots = 'time_slots';
    case TimeBlocks = 'time_blocks';

    public function label(): string
    {
        return match ($this) {
            self::TimeSlots => __('venue-bookings::messages.enums.scheduling_mode.time_slots'),
            self::TimeBlocks => __('venue-bookings::messages.enums.scheduling_mode.time_blocks'),
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
