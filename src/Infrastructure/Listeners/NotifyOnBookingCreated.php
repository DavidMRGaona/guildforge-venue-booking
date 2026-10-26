<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Listeners;

use App\Infrastructure\Authorization\Services\PermissionHolderFinder;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Events\BookingCreated;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Notifications\BookingCreatedNotification;

final readonly class NotifyOnBookingCreated
{
    private const string MANAGE_PERMISSION = 'venuebookings:bookings.manage';

    public function __construct(
        private BookingSettingsReader $settingsReader,
        private BookableResourceRepositoryInterface $resourceRepository,
        private PermissionHolderFinder $permissionHolders,
    ) {}

    public function handle(BookingCreated $event): void
    {
        if (! $this->settingsReader->isNotifyOnBookingCreatedEnabled()) {
            return;
        }

        $resource = $this->resourceRepository->find(
            BookableResourceId::fromString($event->resourceId),
        );

        if (! $resource instanceof BookableResource) {
            return;
        }

        /** @var UserModel|null $user */
        $user = UserModel::find($event->userId);
        $userName = $user->name ?? '';

        $notification = new BookingCreatedNotification(
            bookingId: $event->bookingId,
            userName: $userName,
            resourceName: $resource->name,
            date: $event->date,
            startTime: $event->startTime,
            endTime: $event->endTime,
        );

        // Whoever can confirm or reject it, as authorization sees them: a paused role does not count
        foreach ($this->permissionHolders->usersWhoCan(self::MANAGE_PERMISSION) as $manager) {
            $manager->notify($notification);
        }
    }
}
