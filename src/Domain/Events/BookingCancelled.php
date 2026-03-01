<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BookingCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $bookingId,
        public readonly string $userId,
        public readonly ?string $reason,
        public readonly bool $wasConfirmed,
    ) {
    }
}
