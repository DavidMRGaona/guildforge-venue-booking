<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Repositories;

use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;

interface BookingRepositoryInterface
{
    public function save(Booking $booking): void;

    public function find(BookingId $id): ?Booking;

    public function findOrFail(BookingId $id): Booking;

    public function delete(BookingId $id): void;

    /**
     * @return array<Booking>
     */
    public function getByResourceAndDate(BookableResourceId $resourceId, string $date): array;

    /**
     * @return array<Booking>
     */
    public function getByUser(string $userId): array;

    /**
     * @return array<Booking>
     */
    public function getByStatus(BookingStatus $status): array;

    /**
     * Count active (non-final) bookings for a user.
     */
    public function countActiveByUser(string $userId): int;

    /**
     * Check if a time range on a date overlaps an active booking of the resource, also one of
     * the day before or after when either range crosses midnight (see Booking::overlaps()).
     */
    public function hasOverlappingBooking(
        BookableResourceId $resourceId,
        string $date,
        TimeRange $timeRange,
        ?BookingId $excludeBookingId = null,
    ): bool;

    /**
     * Get confirmed bookings not reminded yet whose start (date and start time,
     * read in the venue timezone) falls between $from and $to, instants in any zone.
     *
     * @return array<Booking>
     */
    public function getConfirmedBookingsForReminder(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array;

    public function markReminderSent(BookingId $id, \DateTimeInterface $sentAt): void;

    /**
     * Confirmed bookings whose end (date and end time read in the venue timezone,
     * on the next day when the range crosses midnight) is at or before $endedBy,
     * ordered by date and start time.
     *
     * @return array<Booking>
     */
    public function getConfirmedEndedBy(\DateTimeInterface $endedBy): array;

    /**
     * @return array<Booking>
     */
    public function getByResourceInDateRange(
        BookableResourceId $resourceId,
        string $fromDate,
        string $toDate,
    ): array;
}
