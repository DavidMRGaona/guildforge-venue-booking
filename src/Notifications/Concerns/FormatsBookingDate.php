<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Notifications\Concerns;

use Carbon\CarbonImmutable;
use Throwable;

trait FormatsBookingDate
{
    /**
     * The booking day as people read it (16/03/2026); the stored value if it cannot be parsed.
     */
    private function displayDate(string $date): string
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $date)?->format('d/m/Y') ?? $date;
        } catch (Throwable) {
            return $date;
        }
    }
}
