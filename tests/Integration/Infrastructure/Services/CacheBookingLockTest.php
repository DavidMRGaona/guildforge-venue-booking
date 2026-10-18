<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;
use Modules\VenueBookings\Infrastructure\Services\CacheBookingLock;
use RuntimeException;
use Tests\TestCase;

/**
 * Creating a booking holds a lock of the user and one of the resource and day, so two requests
 * cannot both pass the active booking limit or the overlap check. The nested calls below act as
 * a second request arriving while the first one holds the locks.
 */
final class CacheBookingLockTest extends TestCase
{
    private const string USER = 'user-1';

    private const string RESOURCE = 'resource-1';

    private const string DAY = '2026-11-12';

    private CacheBookingLock $lock;

    protected function setUp(): void
    {
        parent::setUp();

        // Give up at once instead of waiting for the other request
        $this->lock = new CacheBookingLock(waitSeconds: 0);
    }

    public function test_it_runs_the_callback_and_returns_its_value(): void
    {
        $this->assertSame('booked', $this->lock->forBooking(self::USER, self::RESOURCE, self::DAY, fn (): string => 'booked'));
    }

    public function test_the_same_resource_and_day_waits_for_the_first_booking(): void
    {
        $this->expectException(BookingBusyException::class);

        $this->lock->forBooking(self::USER, self::RESOURCE, self::DAY, fn (): mixed => $this->lock->forBooking(
            'user-2', self::RESOURCE, self::DAY, fn (): string => 'booked',
        ));
    }

    public function test_the_same_user_waits_even_for_another_resource(): void
    {
        $this->expectException(BookingBusyException::class);

        $this->lock->forBooking(self::USER, self::RESOURCE, self::DAY, fn (): mixed => $this->lock->forBooking(
            self::USER, 'resource-2', '2026-11-13', fn (): string => 'booked',
        ));
    }

    public function test_other_users_book_other_resources_and_days_meanwhile(): void
    {
        $result = $this->lock->forBooking(self::USER, self::RESOURCE, self::DAY, fn (): array => [
            $this->lock->forBooking('user-2', 'resource-2', self::DAY, fn (): string => 'other resource'),
            $this->lock->forBooking('user-3', self::RESOURCE, '2026-11-13', fn (): string => 'other day'),
        ]);

        $this->assertSame(['other resource', 'other day'], $result);
    }

    public function test_the_locks_are_released_after_the_booking_even_when_it_fails(): void
    {
        $this->lock->forBooking(self::USER, self::RESOURCE, self::DAY, fn (): string => 'booked');

        $this->assertThrows(
            fn (): mixed => $this->lock->forBooking(self::USER, self::RESOURCE, self::DAY, function (): never {
                throw new RuntimeException('Refused');
            }),
            RuntimeException::class,
        );

        $this->assertSame('booked again', $this->lock->forBooking(self::USER, self::RESOURCE, self::DAY, fn (): string => 'booked again'));
    }

    public function test_the_user_lock_is_released_when_the_resource_is_busy(): void
    {
        $this->assertThrows(
            fn (): mixed => $this->lock->forBooking('user-2', self::RESOURCE, self::DAY, fn (): mixed => $this->lock->forBooking(
                self::USER, self::RESOURCE, self::DAY, fn (): string => 'booked',
            )),
            BookingBusyException::class,
        );

        $this->assertSame('booked', $this->lock->forBooking(self::USER, 'resource-2', self::DAY, fn (): string => 'booked'));
    }
}
