<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\DTOs\MoveBookingDTO;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Application\Services\BookingLockInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Application\Services\SlotAvailabilityServiceInterface;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Modules\VenueBookings\Domain\Events\BookingCancelled;
use Modules\VenueBookings\Domain\Events\BookingCompleted;
use Modules\VenueBookings\Domain\Events\BookingConfirmed;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Events\BookingNoShow;
use Modules\VenueBookings\Domain\Events\BookingRejected;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException;
use Modules\VenueBookings\Domain\Exceptions\BookingCancellationNotAllowedException;
use Modules\VenueBookings\Domain\Exceptions\BookingNotCancellableException;
use Modules\VenueBookings\Domain\Exceptions\MaximumSlotsExceededException;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotOutsideScheduleException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
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
        private AuthorizationServiceInterface $authorization,
        private BookingRules $rules,
        private BookableResourceRepositoryInterface $resourceRepository,
        private BookingLockInterface $lock,
    ) {}

    public function createBooking(CreateBookingDTO $dto): BookingResponseDTO
    {
        return $this->create($dto, applySettingsRules: true);
    }

    public function createBookingAsStaff(CreateBookingDTO $dto): BookingResponseDTO
    {
        return $this->create($dto, applySettingsRules: false);
    }

    public function moveBookingAsStaff(MoveBookingDTO $dto): BookingResponseDTO
    {
        $booking = $this->bookingRepository->findOrFail(BookingId::fromString($dto->bookingId));
        $resourceId = BookableResourceId::fromString($dto->resourceId);
        $timeRange = new TimeRange($dto->startTime, $dto->endTime);
        $moved = $booking->movedTo($resourceId, $dto->date, $timeRange);

        // A finished booking takes up no slot: the staff correct its record freely
        if ($booking->status->isFinal()) {
            $this->bookingRepository->save($moved);

            return BookingResponseDTO::fromEntity($moved);
        }

        $resource = $this->resourceRepository->find($resourceId);

        if (! $resourceId->equals($booking->resourceId) && $resource !== null && ! $resource->isActive()) {
            throw BookableResourceUnavailableException::withId($dto->resourceId);
        }

        $lockedResourceId = strtolower($resource !== null ? $resource->id->value : $dto->resourceId);

        $this->lock->forBooking($booking->userId, $lockedResourceId, function () use ($booking, $moved, $dto, $resourceId, $timeRange): void {
            // A booking keeping its start may be lengthened or change room while under way
            if ($moved->startsAtAnotherTimeThan($booking) && $this->hasStarted($moved)) {
                throw BookingAlreadyStartedException::forSlot($dto->date, $dto->startTime);
            }

            $this->assertFitsSchedule($resourceId, $dto->date, $timeRange);

            if ($this->bookingRepository->hasOverlappingBooking($resourceId, $dto->date, $timeRange, $booking->id)) {
                throw SlotUnavailableException::forTimeRange($dto->date, $dto->startTime, $dto->endTime);
            }

            $this->bookingRepository->save($moved);
        });

        return BookingResponseDTO::fromEntity($moved);
    }

    private function create(CreateBookingDTO $dto, bool $applySettingsRules): BookingResponseDTO
    {
        $timeRange = new TimeRange($dto->startTime, $dto->endTime);
        $resourceId = BookableResourceId::fromString($dto->resourceId);

        // Only active resources are offered on the booking page. An unknown one is refused below,
        // where it has no schedule.
        $resource = $this->resourceRepository->find($resourceId);

        if ($resource !== null && ! $resource->isActive()) {
            throw BookableResourceUnavailableException::withId($dto->resourceId);
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

        // Checked and saved under the lock: two requests cannot both pass the active booking
        // limit or the overlap check. The events go out once the lock is released.
        // Locked by the stored id of the resource when it exists, not by the id as it was sent
        $lockedResourceId = strtolower($resource !== null ? $resource->id->value : $dto->resourceId);

        $this->lock->forBooking($dto->userId, $lockedResourceId, function () use ($booking, $dto, $resourceId, $timeRange, $applySettingsRules): void {
            // Whoever asks, a slot that has already started cannot be booked
            if ($this->hasStarted($booking)) {
                throw BookingAlreadyStartedException::forSlot($dto->date, $dto->startTime);
            }

            // The rules of the settings share the space among members booking on the website,
            // administrators included; staff booking in the panel make the exception on purpose
            if ($applySettingsRules) {
                $this->rules->assertCanBook($dto->userId, $dto->date, $dto->startTime);
            }

            $this->assertFitsSchedule($resourceId, $dto->date, $timeRange);

            if (! $this->slotAvailabilityService->isSlotAvailable($dto->resourceId, $dto->date, $timeRange)) {
                throw SlotUnavailableException::forTimeRange($dto->date, $dto->startTime, $dto->endTime);
            }

            $this->bookingRepository->save($booking);
        });

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

        // Permission first: someone who may not cancel never learns the booking exists
        if (! $this->canCancel($dto->userId, $booking->userId)) {
            throw BookingCancellationNotAllowedException::forUser($dto->bookingId, $dto->userId);
        }

        if ($this->hasStarted($booking)) {
            throw BookingCancellationNotAllowedException::alreadyStarted($dto->bookingId);
        }

        if (! $booking->isCancellable()) {
            throw BookingNotCancellableException::withId($dto->bookingId);
        }

        return $this->cancel($booking, $dto->reason);
    }

    public function cancelBookingAsStaff(CancelBookingDTO $dto): BookingResponseDTO
    {
        $booking = $this->bookingRepository->findOrFail(BookingId::fromString($dto->bookingId));

        return $this->cancel($booking, $dto->reason);
    }

    public function canCancel(string $userId, string $bookingOwnerId): bool
    {
        $user = UserModel::query()->find($userId);

        if ($user === null) {
            return false;
        }

        if ($userId === $bookingOwnerId && $this->authorization->can($user, 'venuebookings:bookings.cancel_own')) {
            return true;
        }

        return $this->authorization->can($user, 'venuebookings:bookings.cancel_any');
    }

    private function cancel(Booking $booking, ?string $reason): BookingResponseDTO
    {
        $wasConfirmed = $booking->status === BookingStatus::Confirmed;
        $booking->cancel($reason);
        $this->bookingRepository->save($booking);

        BookingCancelled::dispatch(
            $booking->id->value,
            $booking->userId,
            $reason,
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

    /**
     * The range must be made of the slots the booking page offers for that day: the day is
     * open, the range starts and ends on slot boundaries (one whole block in block mode)
     * and, in slot mode, it spans between the minimum and the maximum consecutive slots.
     */
    private function assertFitsSchedule(BookableResourceId $resourceId, string $date, TimeRange $timeRange): void
    {
        $schedule = $this->scheduleRepository->findByResource($resourceId);
        $slotCount = $schedule?->slotsCoveredBy($date, $timeRange);

        if ($schedule === null || $slotCount === null) {
            throw SlotOutsideScheduleException::forTimeRange($date, $timeRange->startTime, $timeRange->endTime);
        }

        if ($schedule->schedulingMode !== SchedulingMode::TimeSlots) {
            return;
        }

        if ($slotCount < $schedule->minConsecutiveSlots) {
            throw MinimumSlotsNotMetException::forResource($schedule->minConsecutiveSlots, $slotCount);
        }

        // The picker lets at least one slot through even with a stored maximum below 1
        $maximum = max(1, $schedule->maxConsecutiveSlots);

        if ($slotCount > $maximum) {
            throw MaximumSlotsExceededException::forResource($maximum, $slotCount);
        }
    }

    /**
     * Whether the booking has started now, read in the venue timezone.
     */
    private function hasStarted(Booking $booking): bool
    {
        $timezone = $this->settingsReader->getDateTimeZone();

        return $booking->hasStarted(CarbonImmutable::now($timezone), $timezone);
    }
}
