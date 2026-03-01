<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Modules\VenueBookings\Application\DTOs\Response\BookingEligibilityDTO;

interface BookingEligibilityServiceInterface
{
    public function canUserBook(string $userId, string $resourceId, string $date): BookingEligibilityDTO;
}
