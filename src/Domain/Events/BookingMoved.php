<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The staff moved a pending or confirmed booking to another resource, date or time range.
 * The previous values are those it had before; the booking already holds the new ones.
 * Dispatched once the surrounding transaction commits, if there is one.
 */
final readonly class BookingMoved implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $bookingId,
        public string $userId,
        public string $previousResourceId,
        public string $previousDate,
        public string $previousStartTime,
        public string $previousEndTime,
        public bool $notifyOwner = true,
    ) {}
}
