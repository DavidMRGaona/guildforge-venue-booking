<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;

/**
 * The person may cancel their own bookings on the website: the same check as the "my bookings"
 * page and the profile tab, which offer the cancel button only when it passes.
 */
final readonly class CancelsOwnBookingsCapability implements KnowledgeCapabilityInterface
{
    public function __construct(private BookingServiceInterface $bookings) {}

    public function key(): string
    {
        return 'venue-bookings.can_cancel_own_bookings';
    }

    public function passes(object $user): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        $userId = (string) $user->getAuthIdentifier();

        return $this->bookings->canCancel($userId, $userId);
    }
}
