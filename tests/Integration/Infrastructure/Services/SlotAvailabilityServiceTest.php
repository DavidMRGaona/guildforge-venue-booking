<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Infrastructure\Services\SlotAvailabilityService;
use Tests\TestCase;

final class SlotAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private SlotAvailabilityService $service;

    private BookableResourceModel $resource;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('venue-bookings.min_advance_minutes', 0);
        config()->set('venue-bookings.timezone', 'UTC');

        $this->service = new SlotAvailabilityService(
            new EloquentOperatingScheduleRepository,
            new EloquentBookingRepository,
            new BookingSettingsReader,
        );

        $this->resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Room',
            'slug' => 'test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();
    }

    public function test_it_returns_all_slots_available_when_no_bookings_exist(): void
    {
        $this->createScheduleWithMondayOpen();

        // 2026-03-16 is a Monday (day_of_week=1)
        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        $this->assertNotEmpty($slots);

        foreach ($slots as $slot) {
            $this->assertTrue($slot->isAvailable);
        }
    }

    public function test_it_returns_correct_number_of_slots_based_on_schedule(): void
    {
        // Schedule: open 10:00-14:00 with 60-minute slots = 4 slots
        $this->createScheduleWithMondayOpen();

        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        $this->assertCount(4, $slots);
        $this->assertEquals('10:00', $slots[0]->startTime);
        $this->assertEquals('11:00', $slots[0]->endTime);
        $this->assertEquals('13:00', $slots[3]->startTime);
        $this->assertEquals('14:00', $slots[3]->endTime);
    }

    public function test_it_marks_slots_as_unavailable_when_overlapping_bookings_exist(): void
    {
        $this->createScheduleWithMondayOpen();

        // Create a confirmed booking for 11:00-12:00 on Monday 2026-03-16
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        $this->assertCount(4, $slots);

        // Slot 10:00-11:00 should be available
        $this->assertTrue($slots[0]->isAvailable);
        // Slot 11:00-12:00 should be unavailable (overlaps with booking)
        $this->assertFalse($slots[1]->isAvailable);
        // Slot 12:00-13:00 should be available
        $this->assertTrue($slots[2]->isAvailable);
        // Slot 13:00-14:00 should be available
        $this->assertTrue($slots[3]->isAvailable);
    }

    public function test_it_returns_empty_when_no_schedule_exists_for_resource(): void
    {
        // No operating schedule created for the resource
        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        $this->assertEmpty($slots);
    }

    public function test_it_returns_empty_on_non_operating_day(): void
    {
        $this->createScheduleWithMondayOpen();

        // 2026-03-17 is a Tuesday (day_of_week=2) - no schedule for Tuesday
        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-17');

        $this->assertEmpty($slots);
    }

    public function test_it_does_not_mark_cancelled_bookings_as_unavailable(): void
    {
        $this->createScheduleWithMondayOpen();

        // Create a cancelled booking for 11:00-12:00
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '11:00',
            'end_time' => '12:00',
            'status' => 'cancelled',
        ]);

        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        // All slots should be available since cancelled bookings have final status
        foreach ($slots as $slot) {
            $this->assertTrue($slot->isAvailable);
        }
    }

    public function test_is_slot_available_returns_true_when_no_overlapping_bookings(): void
    {
        $timeRange = new TimeRange(startTime: '10:00', endTime: '12:00');

        $result = $this->service->isSlotAvailable($this->resource->id, '2026-03-16', $timeRange);

        $this->assertTrue($result);
    }

    public function test_is_slot_available_returns_false_when_overlapping_booking_exists(): void
    {
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $timeRange = new TimeRange(startTime: '11:00', endTime: '13:00');

        $result = $this->service->isSlotAvailable($this->resource->id, '2026-03-16', $timeRange);

        $this->assertFalse($result);
    }

    public function test_slots_before_current_time_are_unavailable(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 11, 30));

        $this->createScheduleWithMondayOpen();

        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        $this->assertCount(4, $slots);

        // 10:00-11:00 slot has passed (10:00 < 11:30)
        $this->assertFalse($slots[0]->isAvailable);
        // 11:00-12:00 slot has passed (11:00 < 11:30)
        $this->assertFalse($slots[1]->isAvailable);
        // 12:00-13:00 slot is in the future
        $this->assertTrue($slots[2]->isAvailable);
        // 13:00-14:00 slot is in the future
        $this->assertTrue($slots[3]->isAvailable);
    }

    public function test_slots_within_min_advance_minutes_are_unavailable(): void
    {
        config()->set('venue-bookings.min_advance_minutes', 60);

        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 10, 30));

        $this->createScheduleWithMondayOpen();

        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        $this->assertCount(4, $slots);

        // 10:00 slot is past (10:00 < 10:30)
        $this->assertFalse($slots[0]->isAvailable);
        // 11:00 slot is within advance window (11:00 < 10:30 + 60min = 11:30)
        $this->assertFalse($slots[1]->isAvailable);
        // 12:00 slot is beyond advance window
        $this->assertTrue($slots[2]->isAvailable);
        // 13:00 slot is beyond advance window
        $this->assertTrue($slots[3]->isAvailable);
    }

    public function test_all_slots_available_for_future_dates(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 11, 30));

        $this->createScheduleWithMondayOpen();

        // Next Monday (2026-03-23) is a future date, not today
        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-23');

        $this->assertCount(4, $slots);

        // All slots should be available for a future date
        $this->assertTrue($slots[0]->isAvailable);
        $this->assertTrue($slots[1]->isAvailable);
        $this->assertTrue($slots[2]->isAvailable);
        $this->assertTrue($slots[3]->isAvailable);
    }

    public function test_min_advance_zero_only_filters_past_slots(): void
    {
        config()->set('venue-bookings.min_advance_minutes', 0);

        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 11, 30));

        $this->createScheduleWithMondayOpen();

        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        $this->assertCount(4, $slots);

        // 10:00 slot is past (10:00 < 11:30)
        $this->assertFalse($slots[0]->isAvailable);
        // 11:00 slot is past (11:00 < 11:30)
        $this->assertFalse($slots[1]->isAvailable);
        // 12:00 slot is in the future
        $this->assertTrue($slots[2]->isAvailable);
        // 13:00 slot is in the future
        $this->assertTrue($slots[3]->isAvailable);
    }

    public function test_slots_use_configured_timezone_for_cutoff(): void
    {
        config()->set('venue-bookings.timezone', 'Europe/Madrid');
        config()->set('venue-bookings.min_advance_minutes', 60);

        // 09:30 UTC = 10:30 Europe/Madrid (CET, UTC+1 in March).
        // With 60 min advance, cutoff = 11:30 Madrid time.
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 9, 30, 0, 'UTC'));

        $this->createScheduleWithMondayOpen();

        $slots = $this->service->getAvailableSlotsForDate($this->resource->id, '2026-03-16');

        $this->assertCount(4, $slots);

        // 10:00 < 11:30 Madrid → unavailable
        $this->assertFalse($slots[0]->isAvailable);
        // 11:00 < 11:30 Madrid → unavailable
        $this->assertFalse($slots[1]->isAvailable);
        // 12:00 >= 11:30 Madrid → available
        $this->assertTrue($slots[2]->isAvailable);
        // 13:00 >= 11:30 Madrid → available
        $this->assertTrue($slots[3]->isAvailable);
    }

    /**
     * Creates an operating schedule with Monday open 10:00-14:00, 60-minute slots.
     */
    private function createScheduleWithMondayOpen(): void
    {
        OperatingScheduleModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 4,
            'day_schedules' => [
                [
                    'day_of_week' => 1,
                    'open_time' => '10:00',
                    'close_time' => '14:00',
                    'is_enabled' => true,
                ],
            ],
        ]);
    }
}
