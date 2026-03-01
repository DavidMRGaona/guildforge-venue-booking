<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs;

final readonly class CancelBookingDTO
{
    public function __construct(
        public string $bookingId,
        public string $userId,
        public ?string $reason = null,
    ) {
    }
}
