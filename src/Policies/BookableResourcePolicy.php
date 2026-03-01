<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;

final class BookableResourcePolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function view(UserModel $user, BookableResourceModel $bookableResource): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function update(UserModel $user, BookableResourceModel $bookableResource): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function delete(UserModel $user, BookableResourceModel $bookableResource): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function deleteAny(UserModel $user): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function restore(UserModel $user, BookableResourceModel $bookableResource): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function forceDelete(UserModel $user, BookableResourceModel $bookableResource): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }
}
