<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Application\Services\SlotAvailabilityServiceInterface;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Events\BookingCancelled;
use Modules\VenueBookings\Domain\Events\BookingCompleted;
use Modules\VenueBookings\Domain\Events\BookingConfirmed;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Events\BookingNoShow;
use Modules\VenueBookings\Domain\Events\BookingRejected;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\OperatingScheduleRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldValue;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;

final readonly class BookingService implements BookingServiceInterface
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository,
        private SlotAvailabilityServiceInterface $slotAvailabilityService,
        private BookingSettingsReader $settingsReader,
        private OperatingScheduleRepositoryInterface $scheduleRepository,
    ) {}

    public function createBooking(CreateBookingDTO $dto): BookingResponseDTO
    {
        $timeRange = new TimeRange($dto->startTime, $dto->endTime);
        $resourceId = BookableResourceId::fromString($dto->resourceId);

        $this->validateMinConsecutiveSlots($resourceId, $timeRange);

        if (! $this->slotAvailabilityService->isSlotAvailable($dto->resourceId, $dto->date, $timeRange)) {
            throw SlotUnavailableException::forTimeRange($dto->date, $dto->startTime, $dto->endTime);
        }

        $initialStatus = $this->settingsReader->requiresApproval()
            ? BookingStatus::Pending
            : BookingStatus::Confirmed;

        $fieldValues = array_map(
            fn (array $data): BookingFieldValue => BookingFieldValue::fromArray($data),
            $dto->fieldValues,
        );

        $booking = new Booking(
            id: BookingId::generate(),
            resourceId: $resourceId,
            userId: $dto->userId,
            date: $dto->date,
            timeRange: $timeRange,
            status: $initialStatus,
            eventId: $dto->eventId,
            gameTableId: $dto->gameTableId,
            tournamentId: $dto->tournamentId,
            campaignId: $dto->campaignId,
            fieldValues: $fieldValues,
            confirmedAt: $initialStatus === BookingStatus::Confirmed
                ? new \DateTimeImmutable
                : null,
        );

        $this->bookingRepository->save($booking);

        BookingCreated::dispatch(
            $booking->id->value,
            $booking->resourceId->value,
            $booking->userId,
            $booking->date,
            $booking->timeRange->startTime,
            $booking->timeRange->endTime,
        );

        if ($initialStatus === BookingStatus::Confirmed) {
            BookingConfirmed::dispatch($booking->id->value, $booking->userId);
        }

        return BookingResponseDTO::fromEntity($booking);
    }

    public function confirmBooking(string $bookingId): BookingResponseDTO
    {
        $booking = $this->bookingRepository->findOrFail(BookingId::fromString($bookingId));
        $booking->confirm();
        $this->bookingRepository->save($booking);

        BookingConfirmed::dispatch($booking->id->value, $booking->userId);

        return BookingResponseDTO::fromEntity($booking);
    }

    public function cancelBooking(CancelBookingDTO $dto): BookingResponseDTO
    {
        $booking = $this->bookingRepository->findOrFail(BookingId::fromString($dto->bookingId));
        $wasConfirmed = $booking->status === BookingStatus::Confirmed;
        $booking->cancel($dto->reason);
        $this->bookingRepository->save($booking);

        BookingCancelled::dispatch(
            $booking->id->value,
            $booking->userId,
            $dto->reason,
            $wasConfirmed,
        );

        return BookingResponseDTO::fromEntity($booking);
    }

    public function rejectBooking(string $bookingId, ?string $reason = null): BookingResponseDTO
    {
        $booking = $this->bookingRepository->findOrFail(BookingId::fromString($bookingId));
        $booking->reject($reason);
        $this->bookingRepository->save($booking);

        BookingRejected::dispatch($booking->id->value, $booking->userId, $reason);

        return BookingResponseDTO::fromEntity($booking);
    }

    public function markCompleted(string $bookingId): BookingResponseDTO
    {
        $booking = $this->bookingRepository->findOrFail(BookingId::fromString($bookingId));
        $booking->markCompleted();
        $this->bookingRepository->save($booking);

        BookingCompleted::dispatch($booking->id->value, $booking->userId);

        return BookingResponseDTO::fromEntity($booking);
    }

    public function markNoShow(string $bookingId): BookingResponseDTO
    {
        $booking = $this->bookingRepository->findOrFail(BookingId::fromString($bookingId));
        $booking->markNoShow();
        $this->bookingRepository->save($booking);

        BookingNoShow::dispatch($booking->id->value, $booking->userId);

        return BookingResponseDTO::fromEntity($booking);
    }

    private function validateMinConsecutiveSlots(BookableResourceId $resourceId, TimeRange $timeRange): void
    {
        $schedule = $this->scheduleRepository->findByResource($resourceId);

        if ($schedule === null || $schedule->minConsecutiveSlots <= 1) {
            return;
        }

        $startSeconds = (int) strtotime($timeRange->startTime) - (int) strtotime('00:00');
        $endSeconds = (int) strtotime($timeRange->endTime) - (int) strtotime('00:00');

        if ($endSeconds <= $startSeconds) {
            $endSeconds += 86400;
        }

        $slotCount = (int) (($endSeconds - $startSeconds) / ($schedule->slotDurationMinutes * 60));

        if ($slotCount < $schedule->minConsecutiveSlots) {
            throw MinimumSlotsNotMetException::forResource($schedule->minConsecutiveSlots, $slotCount);
        }
    }
}
