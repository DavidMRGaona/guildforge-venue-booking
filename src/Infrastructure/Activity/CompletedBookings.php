<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Activity;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;

/**
 * What "a booking that counts" means, once: the status `completed`, nothing else. Pending,
 * confirmed, cancelled, rejected, expired and no-show bookings never count, and a confirmed one
 * does not count merely because it is over: it counts once it is completed, which the hourly
 * `venue-bookings:complete-past` task (or the staff) writes. The activity source and the publisher
 * both ask it, so the two can never disagree.
 *
 * It knows nothing about who is looking: the source answers the same for everybody.
 */
final readonly class CompletedBookings
{
    public function __construct(
        private BookingSettingsReader $settings,
    ) {}

    /**
     * @return Builder<BookingModel>
     */
    public function query(): Builder
    {
        return BookingModel::query()->where('status', BookingStatus::Completed->value);
    }

    /**
     * Whether a stored status is the one that counts.
     */
    public function counts(mixed $status): bool
    {
        return ($status instanceof BookingStatus ? $status->value : $status) === BookingStatus::Completed->value;
    }

    /**
     * When the booking ended: its day and end time are wall-clock values of the venue, read in the
     * venue timezone (a range that crosses midnight ends the next day).
     */
    public function endedAt(BookingModel $booking): DateTimeImmutable
    {
        return Booking::endOf(
            $booking->date->format('Y-m-d'),
            new TimeRange($booking->start_time, $booking->end_time),
            $this->settings->getDateTimeZone(),
        );
    }
}
