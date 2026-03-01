<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs;

final readonly class UpdateOperatingScheduleDTO
{
    /**
     * @param  array<array{day_of_week: int, open_time: string, close_time: string, is_enabled: bool}>  $daySchedules
     */
    public function __construct(
        public string $resourceId,
        public int $slotDurationMinutes = 60,
        public int $minConsecutiveSlots = 1,
        public int $maxConsecutiveSlots = 4,
        public array $daySchedules = [],
    ) {
    }
}
