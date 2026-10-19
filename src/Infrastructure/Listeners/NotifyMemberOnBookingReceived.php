<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Notifications\BookingReceivedNotification;

/**
 * A booking that waits for approval tells its member it arrived; an automatically confirmed
 * one is announced by the confirmation email instead.
 */
final readonly class NotifyMemberOnBookingReceived
{
    public function __construct(
        private BookingSettingsReader $settingsReader,
        private BookingRepositoryInterface $bookingRepository,
        private BookableResourceRepositoryInterface $resourceRepository,
    ) {}

    public function handle(BookingCreated $event): void
    {
        if (! $this->settingsReader->isNotifyOnBookingReceivedEnabled()) {
            return;
        }

        $booking = $this->bookingRepository->find(BookingId::fromString($event->bookingId));

        if ($booking === null || $booking->status !== BookingStatus::Pending) {
            return;
        }

        $resource = $this->resourceRepository->find(BookableResourceId::fromString($event->resourceId));
        $user = UserModel::find($event->userId);

        if ($resource === null || $user === null) {
            return;
        }

        $user->notify(new BookingReceivedNotification(
            bookingId: $event->bookingId,
            userName: $user->name,
            resourceName: $resource->name,
            date: $event->date,
            startTime: $event->startTime,
            endTime: $event->endTime,
        ));
    }
}
