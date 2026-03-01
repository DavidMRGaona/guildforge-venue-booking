<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Domain\Events\BookingCancelled;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Notifications\BookingCancelledNotification;

final readonly class NotifyOnBookingCancelled
{
    public function __construct(
        private BookingSettingsReader $settingsReader,
        private BookingRepositoryInterface $bookingRepository,
        private BookableResourceRepositoryInterface $resourceRepository,
    ) {}

    public function handle(BookingCancelled $event): void
    {
        if (! $this->settingsReader->isNotifyOnBookingCancelledEnabled()) {
            return;
        }

        $booking = $this->bookingRepository->find(BookingId::fromString($event->bookingId));

        if ($booking === null) {
            return;
        }

        $resource = $this->resourceRepository->find($booking->resourceId);

        if ($resource === null) {
            return;
        }

        $user = UserModel::find($booking->userId);

        if ($user === null) {
            return;
        }

        $user->notify(new BookingCancelledNotification(
            bookingId: $event->bookingId,
            userName: $user->name,
            resourceName: $resource->name,
            date: $booking->date,
            startTime: $booking->timeRange->startTime,
            endTime: $booking->timeRange->endTime,
            reason: $event->reason,
        ));
    }
}
