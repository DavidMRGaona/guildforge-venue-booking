<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\VenueBookings\Application\Services\BookingLockInterface;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;

/**
 * Atomic cache locks, shared by every app server through the cache store. The user's lock is
 * always taken before the resource's, so two requests never wait for each other in a cycle.
 * Inside a database transaction they are held until it commits or rolls back: the booking saved
 * under them is invisible to other requests until then, and their overlap check must not pass
 * it by (an approved event proposal books its space inside the approval's transaction).
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
            $this->releaseOnceSaved($lock);
        }
    }

    /**
     * Released at once outside a transaction; otherwise when the outermost one commits or rolls
     * back, and in any case after LOCK_SECONDS. Releasing twice does nothing: the lock checks
     * its owner.
     */
    private function releaseOnceSaved(Lock $lock): void
    {
        if (DB::transactionLevel() === 0) {
            $lock->release();

            return;
        }

        $release = static function () use ($lock): void {
            $lock->release();
        };

        DB::afterCommit($release);
        DB::afterRollBack($release);
    }
}
