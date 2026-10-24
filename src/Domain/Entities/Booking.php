<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Entities;

use DateTimeImmutable;
use DateTimeZone;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;

final class Booking
{
    public function __construct(
        public readonly BookingId $id,
        public readonly BookableResourceId $resourceId,
        public readonly string $userId,
        public readonly string $date,
        public readonly TimeRange $timeRange,
        public BookingStatus $status = BookingStatus::Pending,
        public ?string $eventId = null,
        public ?string $gameTableId = null,
        public ?string $tournamentId = null,
        public ?string $campaignId = null,
        public array $fieldValues = [],
        public ?string $cancellationReason = null,
        public ?string $adminNotes = null,
        public ?DateTimeImmutable $confirmedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
    ) {
    }

    public function confirm(): void
    {
        $this->transitionTo(BookingStatus::Confirmed);
        $this->confirmedAt = new DateTimeImmutable();
    }

    public function cancel(?string $reason = null): void
    {
        $this->transitionTo(BookingStatus::Cancelled);
        $this->cancellationReason = $reason;
        $this->cancelledAt = new DateTimeImmutable();
    }

    public function reject(?string $reason = null): void
    {
        $this->transitionTo(BookingStatus::Rejected);
        $this->cancellationReason = $reason;
    }

    public function markCompleted(): void
    {
        $this->transitionTo(BookingStatus::Completed);
    }

    public function markNoShow(): void
    {
        $this->transitionTo(BookingStatus::NoShow);
    }

    public function isCancellable(): bool
    {
        return $this->status->isActive();
    }

    /**
     * When the booking starts. Date and times are stored as venue wall-clock values,
     * so they are read in the venue timezone the caller passes.
     */
    public function startsAt(DateTimeZone $timezone): DateTimeImmutable
    {
        return new DateTimeImmutable("{$this->date} {$this->timeRange->startTime}", $timezone);
    }

    /**
     * When the booking ends, read in the venue timezone the caller passes. A range that
     * crosses midnight ends on the next day.
     */
    public function endsAt(DateTimeZone $timezone): DateTimeImmutable
    {
        $end = new DateTimeImmutable("{$this->date} {$this->timeRange->endTime}", $timezone);

        return $this->timeRange->crossesMidnight() ? $end->modify('+1 day') : $end;
    }

    /**
     * Whether this booking takes up any of $range on $date. On the same date the times are
     * compared on the clock, as the slot list does; across dates the real start and end decide,
     * so a range that crosses midnight meets the early hours of the next day.
     */
    public function overlaps(string $date, TimeRange $range): bool
    {
        if ($date === $this->date) {
            return $this->timeRange->overlaps($range);
        }

        // Times are wall clock, so UTC only carries them
        $utc = new DateTimeZone('UTC');
        $start = new DateTimeImmutable("{$date} {$range->startTime}", $utc);
        $end = new DateTimeImmutable("{$date} {$range->endTime}", $utc);

        if ($range->crossesMidnight()) {
            $end = $end->modify('+1 day');
        }

        return $this->startsAt($utc) < $end && $this->endsAt($utc) > $start;
    }

    /**
     * Whether the booking has started at $now: from its start time on (inclusive),
     * whatever timezone $now is expressed in.
     */
    public function hasStarted(DateTimeImmutable $now, DateTimeZone $timezone): bool
    {
        return $now >= $this->startsAt($timezone);
    }

    public function belongsTo(string $userId): bool
    {
        return $this->userId === $userId;
    }

    private function transitionTo(BookingStatus $newStatus): void
    {
        if (! $this->status->canTransitionTo($newStatus)) {
            throw InvalidStatusTransitionException::fromTo(
                $this->status->value,
                $newStatus->value,
            );
        }

        $this->status = $newStatus;
    }
}
