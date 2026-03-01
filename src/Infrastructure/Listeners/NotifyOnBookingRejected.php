<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Domain\Events\BookingRejected;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Notifications\BookingRejectedNotification;

final readonly class NotifyOnBookingRejected
{
    public function __construct(
        private BookingSettingsReader $settingsReader,
        private BookingRepositoryInterface $bookingRepository,
        private BookableResourceRepositoryInterface $resourceRepository,
    ) {}

    public function handle(BookingRejected $event): void
    {
        if (! $this->settingsReader->isNotifyOnBookingRejectedEnabled()) {
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

        $user->notify(new BookingRejectedNotification(
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
