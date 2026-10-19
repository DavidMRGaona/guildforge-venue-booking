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
    private const string MANAGE_PERMISSION = 'venuebookings:bookings.manage';

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

        // Whoever can confirm or reject it: the admin role, or a role granted bookings.manage
        $managers = UserModel::query()
            ->whereHas('roles', fn ($roles) => $roles
                ->where('name', 'admin')
                ->orWhereHas('permissions', fn ($permissions) => $permissions->where('key', self::MANAGE_PERMISSION)))
            ->get();

        foreach ($managers as $manager) {
            $manager->notify($notification);
        }
    }
}
