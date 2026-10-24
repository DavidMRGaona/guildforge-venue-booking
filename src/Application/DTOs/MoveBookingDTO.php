<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs;

final readonly class MoveBookingDTO
{
    public function __construct(
        public string $bookingId,
        public string $resourceId,
        public string $date,
        public string $startTime,
        public string $endTime,
    ) {}
}
