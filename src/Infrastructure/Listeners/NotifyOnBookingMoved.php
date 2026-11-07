<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Events\BookingMoved;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Notifications\BookingMovedNotification;

final readonly class NotifyOnBookingMoved
{
    public function __construct(
        private BookingSettingsReader $settingsReader,
        private BookingRepositoryInterface $bookingRepository,
        private BookableResourceRepositoryInterface $resourceRepository,
    ) {}

    public function handle(BookingMoved $event): void
    {
        if (! $event->notifyOwner || ! $this->settingsReader->isNotifyOnBookingMovedEnabled()) {
            return;
        }

        $booking = $this->bookingRepository->find(BookingId::fromString($event->bookingId));

        if (! $booking instanceof Booking || ! $booking->status->isActive()) {
            return;
        }

        $resource = $this->resourceRepository->find($booking->resourceId);

        if (! $resource instanceof BookableResource) {
            return;
        }

        $user = UserModel::find($booking->userId);

        if ($user === null) {
            return;
        }

        // The space it was in may have been deleted since: the email then names the current one
        $previousResource = $this->resourceRepository->find(BookableResourceId::fromString($event->previousResourceId));

        $user->notify(new BookingMovedNotification(
            bookingId: $event->bookingId,
            userName: $user->name,
            resourceName: $resource->name,
            date: $booking->date,
            startTime: $booking->timeRange->startTime,
            endTime: $booking->timeRange->endTime,
            previousResourceName: $previousResource instanceof BookableResource ? $previousResource->name : $resource->name,
            previousDate: $event->previousDate,
            previousStartTime: $event->previousStartTime,
            previousEndTime: $event->previousEndTime,
        ));
    }
}
