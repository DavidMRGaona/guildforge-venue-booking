<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Entities;

use DateTimeImmutable;
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
