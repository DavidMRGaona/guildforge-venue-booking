<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Throwable;

final class CompletePastBookingsCommand extends Command
{
    protected $signature = 'venue-bookings:complete-past';

    protected $description = 'Mark confirmed bookings as completed once their end time plus the configured hours has passed';

    public function handle(
        BookingSettingsReader $settingsReader,
        BookingRepositoryInterface $bookingRepository,
        BookingServiceInterface $bookingService,
    ): int {
        if (! $settingsReader->isAutoCompleteEnabled()) {
            $this->info('Automatic completion is disabled.');

            return Command::SUCCESS;
        }

        // Booking dates and times are wall-clock values of the venue
        $endedBy = CarbonImmutable::now($settingsReader->getDateTimeZone())
            ->subHours($settingsReader->getAutoCompleteAfterHours());

        $completed = 0;

        foreach ($bookingRepository->getConfirmedEndedBy($endedBy) as $candidate) {
            try {
                // Read it again: staff may have changed it since the query
                $current = $bookingRepository->find($candidate->id);

                if ($current === null || $current->status !== BookingStatus::Confirmed) {
                    Log::info('venue-bookings:complete-past skipped a booking that changed meanwhile', [
                        'booking_id' => $candidate->id->value,
                    ]);

                    continue;
                }

                $bookingService->markCompleted($candidate->id->value);
                $completed++;
            } catch (Throwable $e) {
                // One broken booking must not stop the others
                Log::error('venue-bookings:complete-past could not complete a booking', [
                    'booking_id' => $candidate->id->value,
                    'exception' => $e,
                ]);
            }
        }

        $this->info("Completed {$completed} booking(s).");

        return Command::SUCCESS;
    }
}
