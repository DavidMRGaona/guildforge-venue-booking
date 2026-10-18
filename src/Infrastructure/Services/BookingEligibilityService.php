<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Modules\VenueBookings\Application\DTOs\Response\BookingEligibilityDTO;
use Modules\VenueBookings\Application\Services\BookingEligibilityServiceInterface;

final readonly class BookingEligibilityService implements BookingEligibilityServiceInterface
{
    public function __construct(
        private BookingRules $rules,
        private BookingSettingsReader $settingsReader,
    ) {}

    public function canUserBook(string $userId, string $resourceId, string $date): BookingEligibilityDTO
    {
        $reasons = [];

        if ($this->rules->hasReachedBookingLimit($userId)) {
            $reasons[] = __('venue-bookings::messages.eligibility.booking_limit_exceeded', [
                'max' => $this->settingsReader->getMaxActiveBookings(),
            ]);
        }

        // Date-level check: a day is out once all of it is before the minimum advance.
        // The slot list marks the earlier slots of the other days as unavailable.
        if ($this->rules->dayIsTooSoon($date)) {
            $reasons[] = __('venue-bookings::messages.eligibility.too_soon', [
                'minutes' => $this->settingsReader->getMinAdvanceMinutes(),
            ]);
        }

        if ($this->rules->isTooFar($date)) {
            $reasons[] = __('venue-bookings::messages.eligibility.too_far', [
                'days' => $this->settingsReader->getMaxFutureDays(),
            ]);
        }

        if ($reasons !== []) {
            return BookingEligibilityDTO::ineligible($reasons);
        }

        return BookingEligibilityDTO::eligible();
    }
}
