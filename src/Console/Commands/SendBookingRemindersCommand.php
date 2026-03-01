<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Notifications\BookingReminderNotification;

final class SendBookingRemindersCommand extends Command
{
    protected $signature = 'venue-bookings:send-reminders';

    protected $description = 'Send reminder notifications for upcoming bookings';

    public function handle(
        BookingSettingsReader $settingsReader,
        BookingRepositoryInterface $bookingRepository,
        BookableResourceRepositoryInterface $resourceRepository,
    ): int {
        if (! $settingsReader->isReminderEnabled()) {
            $this->info('Reminders are disabled.');

            return Command::SUCCESS;
        }

        $hoursBeforeReminder = $settingsReader->getReminderHoursBefore();
        $now = CarbonImmutable::now();
        $from = $now;
        $to = $now->addHours($hoursBeforeReminder);

        $bookings = $bookingRepository->getConfirmedBookingsForReminder($from, $to);

        if (count($bookings) === 0) {
            $this->info('No bookings need reminders.');

            return Command::SUCCESS;
        }

        $sentCount = 0;

        foreach ($bookings as $booking) {
            $resource = $resourceRepository->find($booking->resourceId);

            if ($resource === null) {
                continue;
            }

            $user = UserModel::find($booking->userId);

            if ($user === null) {
                continue;
            }

            $user->notify(new BookingReminderNotification(
                bookingId: $booking->id->value,
                userName: $user->name,
                resourceName: $resource->name,
                date: $booking->date,
                startTime: $booking->timeRange->startTime,
                endTime: $booking->timeRange->endTime,
            ));

            $sentCount++;
        }

        $this->info("Sent {$sentCount} reminder(s).");

        return Command::SUCCESS;
    }
}
