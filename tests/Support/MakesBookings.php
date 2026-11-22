<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Support;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

/**
 * A room and bookings stored straight through the model, in the venue timezone the activity tests
 * pin (Europe/Madrid, UTC+1 in November), so no test depends on the machine's zone.
 */
trait MakesBookings
{
    protected const string VENUE_TIMEZONE = 'Europe/Madrid';

    protected BookableResourceModel $room;

    protected function setUpRoom(): void
    {
        config()->set('modules.settings.venue-bookings.timezone', self::VENUE_TIMEZONE);

        $this->room = $this->makeRoom('Sala grande');
    }

    protected function makeRoom(string $name): BookableResourceModel
    {
        return BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    protected function makeBooking(
        string $status = 'completed',
        ?UserModel $user = null,
        string $date = '2026-11-10',
        string $start = '18:00',
        string $end = '20:00',
        ?BookableResourceModel $room = null,
    ): BookingModel {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => ($room ?? $this->room)->id,
            'user_id' => ($user ?? UserModel::factory()->create())->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
        ]);
    }
}
