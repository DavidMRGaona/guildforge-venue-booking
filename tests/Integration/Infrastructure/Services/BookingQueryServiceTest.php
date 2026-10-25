<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentBookingRepository;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
use Modules\VenueBookings\Infrastructure\Services\BookingQueryService;
use Modules\VenueBookings\Infrastructure\Services\BookingRules;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Infrastructure\Services\SlotAvailabilityService;
use Tests\TestCase;

final class BookingQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingQueryService $service;

    private BookableResourceModel $resource;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        $bookingRepository = new EloquentBookingRepository();
        $scheduleRepository = new EloquentOperatingScheduleRepository();
        $slotAvailabilityService = new SlotAvailabilityService(
            $scheduleRepository,
            $bookingRepository,
            new BookingRules($bookingRepository, new BookingSettingsReader()),
        );

        $this->service = new BookingQueryService(
            $bookingRepository,
            $slotAvailabilityService,
            new BookingSettingsReader(),
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

    public function test_it_returns_booking_response_dto_by_id(): void
    {
        $bookingId = (string) Str::uuid();

        BookingModel::create([
            'id' => $bookingId,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $result = $this->service->getBookingById($bookingId);

        $this->assertNotNull($result);
        $this->assertInstanceOf(BookingResponseDTO::class, $result);
        $this->assertEquals($bookingId, $result->id);
        $this->assertEquals($this->resource->id, $result->resourceId);
        $this->assertEquals($this->user->id, $result->userId);
        $this->assertEquals('2026-03-16', $result->date);
        $this->assertEquals('10:00', $result->startTime);
        $this->assertEquals('12:00', $result->endTime);
        $this->assertEquals(BookingStatus::Confirmed, $result->status);
    }

    public function test_it_returns_null_when_booking_not_found(): void
    {
        $nonExistentId = (string) Str::uuid();

        $result = $this->service->getBookingById($nonExistentId);

        $this->assertNull($result);
    }

    public function test_it_returns_bookings_for_date(): void
    {
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'confirmed',
        ]);

        // Booking on a different date (should not be included)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-17',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $result = $this->service->getBookingsForDate($this->resource->id, '2026-03-16');

        $this->assertCount(2, $result);
        $this->assertEquals('10:00', $result[0]->startTime);
        $this->assertEquals('14:00', $result[1]->startTime);
    }

    public function test_it_flags_started_bookings_for_a_date(): void
    {
        config()->set('modules.settings.venue-bookings.timezone', 'Europe/Madrid');
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 11, 30, 0, 'Europe/Madrid'));

        foreach (['10:00', '14:00'] as $start) {
            BookingModel::create([
                'id' => (string) Str::uuid(),
                'resource_id' => $this->resource->id,
                'user_id' => $this->user->id,
                'date' => '2026-03-16',
                'start_time' => $start,
                'end_time' => '15:00',
                'status' => 'confirmed',
            ]);
        }

        $result = $this->service->getBookingsForDate($this->resource->id, '2026-03-16');

        $this->assertTrue($result[0]->hasStarted);
        $this->assertFalse($result[1]->hasStarted);
    }

    public function test_it_returns_user_bookings(): void
    {
        $secondUser = UserModel::factory()->create();

        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-18',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'confirmed',
        ]);

        // Booking for a different user (should not be included)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $secondUser->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'pending',
        ]);

        $result = $this->service->getUserBookings($this->user->id);

        $this->assertCount(2, $result);

        // Ordered by date descending
        $this->assertEquals('2026-03-18', $result[0]->date);
        $this->assertEquals('2026-03-16', $result[1]->date);
    }

    public function test_it_returns_calendar_events_for_date_range(): void
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

        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-20',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => 'pending',
        ]);

        // Booking outside the date range (should not be included)
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $result = $this->service->getCalendarEvents($this->resource->id, '2026-03-15', '2026-03-25');

        $this->assertCount(2, $result);
        $this->assertStringContainsString('2026-03-16', $result[0]->start);
        $this->assertStringContainsString('2026-03-20', $result[1]->start);
    }

    public function test_it_returns_available_slots_for_date(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'UTC'));

        // Create an operating schedule: Monday 10:00-13:00 with 60-minute slots = 3 slots
        OperatingScheduleModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 3,
            'day_schedules' => [
                [
                    'day_of_week' => 1,
                    'open_time' => '10:00',
                    'close_time' => '13:00',
                    'is_enabled' => true,
                ],
            ],
        ]);

        // 2026-03-16 is a Monday
        $result = $this->service->getAvailableSlots($this->resource->id, '2026-03-16');

        $this->assertCount(3, $result);
        $this->assertEquals('10:00', $result[0]->startTime);
        $this->assertEquals('11:00', $result[0]->endTime);
        $this->assertTrue($result[0]->isAvailable);
        $this->assertEquals('11:00', $result[1]->startTime);
        $this->assertEquals('12:00', $result[1]->endTime);
        $this->assertEquals('12:00', $result[2]->startTime);
        $this->assertEquals('13:00', $result[2]->endTime);
    }

    public function test_it_returns_empty_available_slots_when_no_schedule(): void
    {
        // No schedule created - 2026-03-16 is a Monday
        $result = $this->service->getAvailableSlots($this->resource->id, '2026-03-16');

        $this->assertEmpty($result);
    }

    public function test_user_bookings_are_cancellable_only_until_they_start(): void
    {
        config()->set('modules.settings.venue-bookings.timezone', 'Europe/Madrid');
        $this->travelTo(CarbonImmutable::create(2026, 3, 16, 11, 0, 0, 'Europe/Madrid'));

        $started = $this->createBookingFor('2026-03-16', '10:00', '12:00', 'confirmed');
        $later = $this->createBookingFor('2026-03-16', '12:00', '14:00', 'pending');
        $cancelled = $this->createBookingFor('2026-03-17', '10:00', '12:00', 'cancelled');

        $flags = [];
        foreach ($this->service->getUserBookings($this->user->id) as $booking) {
            $flags[$booking->id] = $booking->toArray()['is_cancellable'];
        }

        $this->assertFalse($flags[$started]);
        $this->assertTrue($flags[$later]);
        $this->assertFalse($flags[$cancelled]);
    }

    private function createBookingFor(string $date, string $startTime, string $endTime, string $status): string
    {
        $id = (string) Str::uuid();
        BookingModel::create([
            'id' => $id,
            'resource_id' => $this->resource->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
        ]);

        return $id;
    }
}
