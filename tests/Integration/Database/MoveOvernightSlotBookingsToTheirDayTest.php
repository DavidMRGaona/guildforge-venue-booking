<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Bookings of a slot past the midnight of a day open overnight were stored with the day the
 * venue opens; the migration moves them to the day they take place.
 */
final class MoveOvernightSlotBookingsToTheirDayTest extends ModuleTestCase
{
    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    /** The migration is an anonymous class: there is no type to name its up() with. */
    private mixed $migration;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_24_000001_move_overnight_slot_bookings_to_their_day.php';
        $this->user = UserModel::factory()->create();
    }

    public function test_bookings_past_midnight_of_an_overnight_day_move_to_the_next_day(): void
    {
        // Mondays 22:00-02:00 in hourly slots; 2026-03-16 is a Monday
        $room = $this->resourceWith('time_slots', [['day_of_week' => 1, 'open_time' => '22:00', 'close_time' => '02:00', 'is_enabled' => true]]);
        $tail = $this->bookingOn($room, '2026-03-16', '00:00', '02:00');
        $late = $this->bookingOn($room, '2026-03-16', '01:00', '02:00', 'completed');
        $crossing = $this->bookingOn($room, '2026-03-16', '23:00', '01:00');
        $evening = $this->bookingOn($room, '2026-03-16', '22:00', '23:00');

        $this->migration->up();

        $this->assertSame('2026-03-17', $this->dateOf($tail));
        $this->assertSame('2026-03-17', $this->dateOf($late));
        $this->assertSame('2026-03-16', $this->dateOf($crossing));
        $this->assertSame('2026-03-16', $this->dateOf($evening));
    }

    public function test_daytime_and_block_bookings_stay_on_their_date(): void
    {
        $day = $this->resourceWith('time_slots', [['day_of_week' => 1, 'open_time' => '00:00', 'close_time' => '06:00', 'is_enabled' => true]]);
        $blocks = $this->resourceWith('time_blocks', [['day_of_week' => 1, 'open_time' => '00:00', 'close_time' => '03:00', 'is_enabled' => true, 'label' => 'Madrugada']]);
        $early = $this->bookingOn($day, '2026-03-16', '00:00', '01:00');
        $block = $this->bookingOn($blocks, '2026-03-16', '00:00', '03:00');

        $this->migration->up();

        $this->assertSame('2026-03-16', $this->dateOf($early));
        $this->assertSame('2026-03-16', $this->dateOf($block));
    }

    /**
     * @param  array<array<string, mixed>>  $daySchedules
     */
    private function resourceWith(string $mode, array $daySchedules): string
    {
        $id = (string) Str::uuid();
        BookableResourceModel::create(['id' => $id, 'name' => $id, 'slug' => $id, 'status' => 'active', 'sort_order' => 0]);
        OperatingScheduleModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 4,
            'scheduling_mode' => $mode,
            'day_schedules' => $daySchedules,
        ]);

        return $id;
    }

    private function bookingOn(string $resourceId, string $date, string $start, string $end, string $status = 'confirmed'): string
    {
        $id = (string) Str::uuid();
        BookingModel::create([
            'id' => $id,
            'resource_id' => $resourceId,
            'user_id' => $this->user->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
        ]);

        return $id;
    }

    private function dateOf(string $bookingId): string
    {
        return substr((string) DB::table('venuebookings_bookings')->where('id', $bookingId)->value('date'), 0, 10);
    }
}
