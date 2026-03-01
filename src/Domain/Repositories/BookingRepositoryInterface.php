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
     * Check if a time range overlaps with existing active bookings for a resource on a date.
     */
    public function hasOverlappingBooking(
        BookableResourceId $resourceId,
        string $date,
        TimeRange $timeRange,
        ?BookingId $excludeBookingId = null,
    ): bool;

    /**
     * Get confirmed bookings within a reminder window.
     *
     * @return array<Booking>
     */
    public function getConfirmedBookingsForReminder(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array;

    /**
     * @return array<Booking>
     */
    public function getByResourceInDateRange(
        BookableResourceId $resourceId,
        string $fromDate,
        string $toDate,
    ): array;
}
