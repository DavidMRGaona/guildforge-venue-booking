<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Modules\VenueBookings\Domain\Exceptions\BookingLimitExceededException;
use Modules\VenueBookings\Domain\Exceptions\TooFarToBookException;
use Modules\VenueBookings\Domain\Exceptions\TooSoonToBookException;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;

/**
 * The booking rules set in the panel: active bookings per user, minimum advance and
 * days ahead. The eligibility check, the slot list and createBooking() all ask here.
 * Booking dates and times are venue wall-clock values, read in the venue timezone.
 */
final readonly class BookingRules
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository,
        private BookingSettingsReader $settings,
    ) {}

    public function hasReachedBookingLimit(string $userId): bool
    {
        return $this->bookingRepository->countActiveByUser($userId) >= $this->settings->getMaxActiveBookings();
    }

    /**
     * The earliest moment a new booking may start: now plus the minimum advance.
     */
    public function earliestStart(): CarbonImmutable
    {
        return CarbonImmutable::now($this->settings->getDateTimeZone())
            ->addMinutes($this->settings->getMinAdvanceMinutes());
    }

    /**
     * Whether a booking starting at $startTime on $date starts before earliestStart().
     */
    public function startsTooSoon(string $date, string $startTime): bool
    {
        return CarbonImmutable::parse("{$date} {$startTime}", $this->settings->getDateTimeZone())
            ->lt($this->earliestStart());
    }

    /**
     * Whether all of $date is over before earliestStart(), so none of its slots can be booked.
     */
    public function dayIsTooSoon(string $date): bool
    {
        return CarbonImmutable::parse($date, $this->settings->getDateTimeZone())
            ->endOfDay()
            ->lt($this->earliestStart());
    }

    /**
     * Whether $date (Y-m-d) lies after the venue's today plus the maximum days ahead.
     */
    public function isTooFar(string $date): bool
    {
        $lastDay = CarbonImmutable::now($this->settings->getDateTimeZone())
            ->addDays($this->settings->getMaxFutureDays())
            ->toDateString();

        return $date > $lastDay;
    }

    /**
     * @throws BookingLimitExceededException
     * @throws TooSoonToBookException
     * @throws TooFarToBookException
     */
    public function assertCanBook(string $userId, string $date, string $startTime): void
    {
        if ($this->hasReachedBookingLimit($userId)) {
            throw BookingLimitExceededException::forUser($userId, $this->settings->getMaxActiveBookings());
        }

        if ($this->startsTooSoon($date, $startTime)) {
            throw TooSoonToBookException::withMinAdvance($this->settings->getMinAdvanceMinutes());
        }

        if ($this->isTooFar($date)) {
            throw TooFarToBookException::withMaxDays($this->settings->getMaxFutureDays());
        }
    }
}
