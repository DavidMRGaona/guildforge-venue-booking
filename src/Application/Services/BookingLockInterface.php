<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Closure;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;

/**
 * Serialises the creation of bookings: the callback runs while holding a lock of the user (the
 * active booking limit spans every resource) and one of the resource (the overlap check, which
 * reaches the next day for a booking that crosses midnight), so two requests never both pass
 * those checks.
 */
interface BookingLockInterface
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws BookingBusyException when the locks are not free in time
     */
    public function forBooking(string $userId, string $resourceId, Closure $callback): mixed;
}
