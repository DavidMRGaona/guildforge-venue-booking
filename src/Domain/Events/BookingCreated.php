<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class BookingCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $bookingId,
        public string $resourceId,
        public string $userId,
        public string $date,
        public string $startTime,
        public string $endTime,
    ) {}
}
