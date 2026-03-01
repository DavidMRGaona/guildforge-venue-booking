<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Carbon\CarbonImmutable;
use Modules\VenueBookings\Application\DTOs\Response\BookingEligibilityDTO;
use Modules\VenueBookings\Application\Services\BookingEligibilityServiceInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;

final readonly class BookingEligibilityService implements BookingEligibilityServiceInterface
{
    public function __construct(
        private BookingRepositoryInterface $bookingRepository,
        private BookingSettingsReader $settingsReader,
    ) {}

    public function canUserBook(string $userId, string $resourceId, string $date): BookingEligibilityDTO
    {
        $reasons = [];

        $this->checkBookingLimit($userId, $reasons);
        $this->checkMinAdvanceTime($date, $reasons);
        $this->checkMaxFutureDays($date, $reasons);

        if ($reasons !== []) {
            return BookingEligibilityDTO::ineligible($reasons);
        }

        return BookingEligibilityDTO::eligible();
    }

    /**
     * @param  array<string>  $reasons
     */
    private function checkBookingLimit(string $userId, array &$reasons): void
    {
        $maxBookings = $this->settingsReader->getMaxActiveBookings();
        $activeCount = $this->bookingRepository->countActiveByUser($userId);

        if ($activeCount >= $maxBookings) {
            $reasons[] = __('venue-bookings::messages.eligibility.booking_limit_exceeded', [
                'max' => $maxBookings,
            ]);
        }
    }

    /**
     * @param  array<string>  $reasons
     */
    private function checkMinAdvanceTime(string $date, array &$reasons): void
    {
        $tz = $this->settingsReader->getTimezone();
        $minAdvanceMinutes = $this->settingsReader->getMinAdvanceMinutes();
        $now = CarbonImmutable::now($tz);
        $cutoff = $now->addMinutes($minAdvanceMinutes);

        // Use end of day (23:59:59) for the date-level eligibility check.
        // Slot-level time filtering is handled by SlotAvailabilityService.
        $endOfBookingDay = CarbonImmutable::parse($date, $tz)->endOfDay();

        if ($endOfBookingDay->lt($cutoff)) {
            $reasons[] = __('venue-bookings::messages.eligibility.too_soon', [
                'minutes' => $minAdvanceMinutes,
            ]);
        }
    }

    /**
     * @param  array<string>  $reasons
     */
    private function checkMaxFutureDays(string $date, array &$reasons): void
    {
        $tz = $this->settingsReader->getTimezone();
        $maxFutureDays = $this->settingsReader->getMaxFutureDays();
        $bookingDate = CarbonImmutable::parse($date, $tz)->startOfDay();
        $maxDate = CarbonImmutable::now($tz)->addDays($maxFutureDays)->endOfDay();

        if ($bookingDate->gt($maxDate)) {
            $reasons[] = __('venue-bookings::messages.eligibility.too_far', [
                'days' => $maxFutureDays,
            ]);
        }
    }
}
