<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A booking was made, pending or confirmed.
 * Dispatched once the surrounding transaction commits, if there is one: a booking made while an
 * event proposal is approved announces nothing when the approval rolls back.
 */
final readonly class BookingCreated implements ShouldDispatchAfterCommit
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
