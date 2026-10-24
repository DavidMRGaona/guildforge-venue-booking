<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;

/**
 * Bookings of a slot past the midnight of a day open overnight (e.g. 01:00-02:00 of a
 * Friday open 22:00-02:00) were stored with the day the venue opens, so they read as the
 * early hours of that day. They move to the next day, the one they take place on.
 *
 * Only time-slot resources are affected (blocks are read on their own date). The current
 * schedule of each resource decides which slots are past midnight.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schedules = new EloquentOperatingScheduleRepository;

        $resourceIds = DB::table('venuebookings_operating_schedules')
            ->where('scheduling_mode', SchedulingMode::TimeSlots->value)
            ->pluck('resource_id');

        foreach ($resourceIds as $resourceId) {
            $schedule = $schedules->findByResource(BookableResourceId::fromString((string) $resourceId));

            if ($schedule === null) {
                continue;
            }

            $bookings = DB::table('venuebookings_bookings')
                ->where('resource_id', $resourceId)
                ->get(['id', 'date', 'start_time', 'end_time']);

            foreach ($bookings as $booking) {
                $date = substr((string) $booking->date, 0, 10);

                if ((string) $booking->start_time === (string) $booking->end_time) {
                    continue;
                }

                $startDate = $schedule->startDateOf($date, new TimeRange((string) $booking->start_time, (string) $booking->end_time));

                if ($startDate !== $date) {
                    DB::table('venuebookings_bookings')->where('id', $booking->id)->update(['date' => $startDate]);
                }
            }
        }
    }

    public function down(): void
    {
        // Not reversible without knowing which bookings moved; the dates are now the real ones
    }
};
