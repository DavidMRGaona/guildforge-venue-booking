<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class BookingNoShow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $bookingId,
        public string $userId,
    ) {}
}
