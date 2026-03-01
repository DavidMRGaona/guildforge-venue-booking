<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
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

        $this->service = new SlotAvailabilityService(
            new EloquentOperatingScheduleRepository(),
            new EloquentBookingRepository(),
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
