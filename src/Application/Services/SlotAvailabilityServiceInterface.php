<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Modules\VenueBookings\Domain\ValueObjects\BookingSlot;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;

interface SlotAvailabilityServiceInterface
{
    /**
     * @return array<BookingSlot>
     */
    public function getAvailableSlotsForDate(string $resourceId, string $date): array;

    public function isSlotAvailable(string $resourceId, string $date, TimeRange $timeRange): bool;
}
