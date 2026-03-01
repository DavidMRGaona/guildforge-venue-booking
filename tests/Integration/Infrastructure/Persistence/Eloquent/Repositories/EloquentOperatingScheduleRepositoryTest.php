<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\Entities\OperatingSchedule;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceNotFoundException;
use Modules\VenueBookings\Domain\Repositories\OperatingScheduleRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\DaySchedule;
use Modules\VenueBookings\Domain\ValueObjects\OperatingScheduleId;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories\EloquentOperatingScheduleRepository;
use Tests\TestCase;

final class EloquentOperatingScheduleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentOperatingScheduleRepository $repository;

    private BookableResourceModel $resource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentOperatingScheduleRepository();

        $this->resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Schedule Test Room',
            'slug' => 'schedule-test-room',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    public function test_it_implements_repository_interface(): void
    {
        $this->assertInstanceOf(OperatingScheduleRepositoryInterface::class, $this->repository);
    }

    public function test_it_saves_new_schedule(): void
    {
        $scheduleId = OperatingScheduleId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        $daySchedules = [
            new DaySchedule(
                dayOfWeek: 1,
                openTime: '09:00',
                closeTime: '21:00',
                isEnabled: true,
            ),
            new DaySchedule(
                dayOfWeek: 2,
                openTime: '09:00',
                closeTime: '21:00',
                isEnabled: true,
            ),
            new DaySchedule(
                dayOfWeek: 0,
                openTime: '00:00',
                closeTime: '00:00',
                isEnabled: false,
            ),
        ];

        $schedule = new OperatingSchedule(
            id: $scheduleId,
            resourceId: $resourceId,
            slotDurationMinutes: 30,
            minConsecutiveSlots: 2,
            maxConsecutiveSlots: 6,
            daySchedules: $daySchedules,
        );

        $this->repository->save($schedule);

        $this->assertDatabaseHas('venuebookings_operating_schedules', [
            'id' => $scheduleId->value,
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 30,
            'min_consecutive_slots' => 2,
            'max_consecutive_slots' => 6,
        ]);
    }

    public function test_it_updates_existing_schedule(): void
    {
        $scheduleId = OperatingScheduleId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        OperatingScheduleModel::create([
            'id' => $scheduleId->value,
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 4,
            'day_schedules' => [],
        ]);

        $updatedSchedule = new OperatingSchedule(
            id: $scheduleId,
            resourceId: $resourceId,
            slotDurationMinutes: 45,
            minConsecutiveSlots: 2,
            maxConsecutiveSlots: 8,
            daySchedules: [
                new DaySchedule(
                    dayOfWeek: 1,
                    openTime: '10:00',
                    closeTime: '20:00',
                    isEnabled: true,
                ),
            ],
        );

        $this->repository->save($updatedSchedule);

        $this->assertDatabaseHas('venuebookings_operating_schedules', [
            'id' => $scheduleId->value,
            'slot_duration_minutes' => 45,
            'min_consecutive_slots' => 2,
            'max_consecutive_slots' => 8,
        ]);

        $this->assertDatabaseCount('venuebookings_operating_schedules', 1);
    }

    public function test_it_finds_schedule_by_id(): void
    {
        $scheduleId = OperatingScheduleId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        $daySchedulesData = [
            [
                'day_of_week' => 1,
                'open_time' => '09:00',
                'close_time' => '21:00',
                'is_enabled' => true,
            ],
            [
                'day_of_week' => 0,
                'open_time' => '00:00',
                'close_time' => '00:00',
                'is_enabled' => false,
            ],
        ];

        OperatingScheduleModel::create([
            'id' => $scheduleId->value,
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 4,
            'day_schedules' => $daySchedulesData,
        ]);

        $schedule = $this->repository->find($scheduleId);

        $this->assertNotNull($schedule);
        $this->assertTrue($scheduleId->equals($schedule->id));
        $this->assertTrue($resourceId->equals($schedule->resourceId));
        $this->assertEquals(60, $schedule->slotDurationMinutes);
        $this->assertEquals(1, $schedule->minConsecutiveSlots);
        $this->assertEquals(4, $schedule->maxConsecutiveSlots);
        $this->assertCount(2, $schedule->daySchedules);
        $this->assertEquals(1, $schedule->daySchedules[0]->dayOfWeek);
        $this->assertEquals('09:00', $schedule->daySchedules[0]->openTime);
        $this->assertTrue($schedule->daySchedules[0]->isEnabled);
        $this->assertFalse($schedule->daySchedules[1]->isEnabled);
    }

    public function test_it_returns_null_when_schedule_not_found(): void
    {
        $nonExistentId = OperatingScheduleId::generate();

        $schedule = $this->repository->find($nonExistentId);

        $this->assertNull($schedule);
    }

    public function test_find_or_fail_throws_when_not_found(): void
    {
        $nonExistentId = OperatingScheduleId::generate();

        $this->expectException(BookableResourceNotFoundException::class);

        $this->repository->findOrFail($nonExistentId);
    }

    public function test_find_or_fail_returns_entity_when_found(): void
    {
        $scheduleId = OperatingScheduleId::generate();

        OperatingScheduleModel::create([
            'id' => $scheduleId->value,
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 4,
            'day_schedules' => [],
        ]);

        $schedule = $this->repository->findOrFail($scheduleId);

        $this->assertTrue($scheduleId->equals($schedule->id));
    }

    public function test_it_finds_schedule_by_resource(): void
    {
        $scheduleId = OperatingScheduleId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        OperatingScheduleModel::create([
            'id' => $scheduleId->value,
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 30,
            'min_consecutive_slots' => 2,
            'max_consecutive_slots' => 6,
            'day_schedules' => [
                [
                    'day_of_week' => 3,
                    'open_time' => '10:00',
                    'close_time' => '18:00',
                    'is_enabled' => true,
                ],
            ],
        ]);

        $schedule = $this->repository->findByResource($resourceId);

        $this->assertNotNull($schedule);
        $this->assertTrue($scheduleId->equals($schedule->id));
        $this->assertTrue($resourceId->equals($schedule->resourceId));
        $this->assertEquals(30, $schedule->slotDurationMinutes);
    }

    public function test_it_returns_null_when_no_schedule_for_resource(): void
    {
        $resourceId = BookableResourceId::fromString($this->resource->id);

        $schedule = $this->repository->findByResource($resourceId);

        $this->assertNull($schedule);
    }

    public function test_it_finds_correct_schedule_among_multiple_resources(): void
    {
        $secondResource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Second Room',
            'slug' => 'second-room',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        $firstScheduleId = OperatingScheduleId::generate();
        $secondScheduleId = OperatingScheduleId::generate();

        OperatingScheduleModel::create([
            'id' => $firstScheduleId->value,
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 4,
            'day_schedules' => [],
        ]);

        OperatingScheduleModel::create([
            'id' => $secondScheduleId->value,
            'resource_id' => $secondResource->id,
            'slot_duration_minutes' => 30,
            'min_consecutive_slots' => 2,
            'max_consecutive_slots' => 8,
            'day_schedules' => [],
        ]);

        $resourceId = BookableResourceId::fromString($secondResource->id);
        $schedule = $this->repository->findByResource($resourceId);

        $this->assertNotNull($schedule);
        $this->assertTrue($secondScheduleId->equals($schedule->id));
        $this->assertEquals(30, $schedule->slotDurationMinutes);
    }

    public function test_it_deletes_schedule(): void
    {
        $scheduleId = OperatingScheduleId::generate();

        OperatingScheduleModel::create([
            'id' => $scheduleId->value,
            'resource_id' => $this->resource->id,
            'slot_duration_minutes' => 60,
            'min_consecutive_slots' => 1,
            'max_consecutive_slots' => 4,
            'day_schedules' => [],
        ]);

        $this->assertDatabaseHas('venuebookings_operating_schedules', ['id' => $scheduleId->value]);

        $this->repository->delete($scheduleId);

        $this->assertDatabaseMissing('venuebookings_operating_schedules', ['id' => $scheduleId->value]);
    }

    public function test_it_roundtrips_schedule_with_day_schedules(): void
    {
        $scheduleId = OperatingScheduleId::generate();
        $resourceId = BookableResourceId::fromString($this->resource->id);

        $daySchedules = [
            new DaySchedule(dayOfWeek: 1, openTime: '09:00', closeTime: '21:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 2, openTime: '10:00', closeTime: '20:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 3, openTime: '10:00', closeTime: '20:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 4, openTime: '10:00', closeTime: '20:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 5, openTime: '09:00', closeTime: '22:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 6, openTime: '10:00', closeTime: '18:00', isEnabled: true),
            new DaySchedule(dayOfWeek: 0, openTime: '00:00', closeTime: '00:00', isEnabled: false),
        ];

        $schedule = new OperatingSchedule(
            id: $scheduleId,
            resourceId: $resourceId,
            slotDurationMinutes: 45,
            minConsecutiveSlots: 1,
            maxConsecutiveSlots: 3,
            daySchedules: $daySchedules,
        );

        $this->repository->save($schedule);

        $found = $this->repository->find($scheduleId);

        $this->assertNotNull($found);
        $this->assertCount(7, $found->daySchedules);
        $this->assertEquals(1, $found->daySchedules[0]->dayOfWeek);
        $this->assertEquals('09:00', $found->daySchedules[0]->openTime);
        $this->assertEquals('21:00', $found->daySchedules[0]->closeTime);
        $this->assertTrue($found->daySchedules[0]->isEnabled);
        $this->assertEquals(0, $found->daySchedules[6]->dayOfWeek);
        $this->assertFalse($found->daySchedules[6]->isEnabled);
    }
}
