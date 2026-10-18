<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Support;

use Illuminate\Support\Str;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;

/**
 * Gives a resource a schedule open every day of the week in hourly slots, so the bookings a
 * test makes fit the schedule that createBooking() checks.
 */
trait OpensResourceEveryDay
{
    private function openEveryDay(
        string $resourceId,
        string $openTime,
        string $closeTime,
        int $maxConsecutiveSlots = 4,
    ): void {
        OperatingScheduleModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $resourceId,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => $maxConsecutiveSlots,
            'day_schedules' => array_map(
                fn (int $day): array => ['day_of_week' => $day, 'open_time' => $openTime, 'close_time' => $closeTime, 'is_enabled' => true],
                range(0, 6),
            ),
        ]);
    }
}
