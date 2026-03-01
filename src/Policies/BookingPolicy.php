<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Policies;

use App\Infrastructure\Authorization\Policies\AuthorizesWithPermissions;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

final class BookingPolicy
{
    use AuthorizesWithPermissions;

    public function viewAny(UserModel $user): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.view_any');
    }

    public function view(UserModel $user, BookingModel $booking): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.view')
            || $booking->getAttribute('user_id') === $user->id;
    }

    public function create(UserModel $user): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.create');
    }

    public function update(UserModel $user, BookingModel $booking): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function delete(UserModel $user, BookingModel $booking): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function deleteAny(UserModel $user): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function restore(UserModel $user, BookingModel $booking): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }

    public function forceDelete(UserModel $user, BookingModel $booking): bool
    {
        return $this->authorize($user, 'venuebookings:bookings.manage');
    }
}
