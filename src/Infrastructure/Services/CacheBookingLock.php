<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Modules\VenueBookings\Application\Services\BookingLockInterface;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;

/**
 * Atomic cache locks, shared by every app server through the cache store. The user's lock is
 * always taken before the resource's, so two requests never wait for each other in a cycle.
 */
final readonly class CacheBookingLock implements BookingLockInterface
{
    /** Longer than any booking takes to save; a crashed request frees its locks after it. */
    private const int LOCK_SECONDS = 30;

    public function __construct(
        private int $waitSeconds = 5,
    ) {}

    public function forBooking(string $userId, string $resourceId, Closure $callback): mixed
    {
        // Canonical keys: the same user and resource always map to the same locks, however
        // they were written (PostgreSQL's uuid columns match either case)
        $userKey = strtolower(trim($userId));
        $resourceKey = strtolower(trim($resourceId));

        return $this->holding(
            "venue-bookings:booking-lock:user:{$userKey}",
            fn (): mixed => $this->holding("venue-bookings:booking-lock:resource:{$resourceKey}", $callback),
        );
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function holding(string $key, Closure $callback): mixed
    {
        $lock = Cache::lock($key, self::LOCK_SECONDS);

        try {
            $lock->block($this->waitSeconds);
        } catch (LockTimeoutException) {
            throw BookingBusyException::create();
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
