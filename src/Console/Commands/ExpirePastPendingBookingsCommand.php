<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Throwable;

final class ExpirePastPendingBookingsCommand extends Command
{
    protected $signature = 'venue-bookings:expire-past-pending';

    protected $description = 'Mark pending bookings nobody approved as expired once they have ended';

    public function handle(
        BookingSettingsReader $settingsReader,
        BookingRepositoryInterface $bookingRepository,
        BookingServiceInterface $bookingService,
    ): int {
        // Booking dates and times are wall-clock values of the venue
        $now = CarbonImmutable::now($settingsReader->getDateTimeZone());

        $expired = 0;

        foreach ($bookingRepository->getPendingEndedBy($now) as $candidate) {
            try {
                // Read it again: staff may have approved it since the query
                $current = $bookingRepository->find($candidate->id);

                if (! $current instanceof Booking || $current->status !== BookingStatus::Pending) {
                    Log::info('venue-bookings:expire-past-pending skipped a booking that changed meanwhile', [
                        'booking_id' => $candidate->id->value,
                    ]);

                    continue;
                }

                $bookingService->expireBooking($candidate->id->value);
                $expired++;
            } catch (Throwable $e) {
                // One broken booking must not stop the others
                Log::error('venue-bookings:expire-past-pending could not expire a booking', [
                    'booking_id' => $candidate->id->value,
                    'exception' => $e,
                ]);
            }
        }

        $this->info("Expired {$expired} booking(s).");

        return Command::SUCCESS;
    }
}
