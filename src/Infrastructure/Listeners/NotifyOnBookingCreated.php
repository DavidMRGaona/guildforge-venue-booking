<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Listeners;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Notifications\BookingCreatedNotification;

final readonly class NotifyOnBookingCreated
{
    public function __construct(
        private BookingSettingsReader $settingsReader,
        private BookableResourceRepositoryInterface $resourceRepository,
    ) {}

    public function handle(BookingCreated $event): void
    {
        if (! $this->settingsReader->isNotifyOnBookingCreatedEnabled()) {
            return;
        }

        $resource = $this->resourceRepository->find(
            BookableResourceId::fromString($event->resourceId),
        );

        if ($resource === null) {
            return;
        }

        /** @var UserModel|null $user */
        $user = UserModel::find($event->userId);
        $userName = $user !== null ? $user->name : '';

        $notification = new BookingCreatedNotification(
            bookingId: $event->bookingId,
            userName: $userName,
            resourceName: $resource->name,
            date: $event->date,
            startTime: $event->startTime,
            endTime: $event->endTime,
        );

        $admins = UserModel::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }
}
