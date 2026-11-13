<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Notifications\Concerns;

use Modules\VenueBookings\Application\Services\BookingMailSubjects;

trait FormatsBookingDate
{
    /**
     * The booking day as people read it (16/03/2026); the stored value if it cannot be parsed.
     */
    private function displayDate(string $date): string
    {
        return BookingMailSubjects::displayDate($date);
    }
}
