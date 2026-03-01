<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Repositories;

use Modules\VenueBookings\Domain\Entities\OperatingSchedule;
use Modules\VenueBookings\Domain\Enums\SchedulingMode;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceNotFoundException;
use Modules\VenueBookings\Domain\Repositories\OperatingScheduleRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\DaySchedule;
use Modules\VenueBookings\Domain\ValueObjects\OperatingScheduleId;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;

final readonly class EloquentOperatingScheduleRepository implements OperatingScheduleRepositoryInterface
{
    public function save(OperatingSchedule $schedule): void
    {
        OperatingScheduleModel::query()->updateOrCreate(
            ['id' => $schedule->id->value],
            $this->toArray($schedule),
        );
    }

    public function find(OperatingScheduleId $id): ?OperatingSchedule
    {
        $model = OperatingScheduleModel::query()->find($id->value);

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function findOrFail(OperatingScheduleId $id): OperatingSchedule
    {
        $entity = $this->find($id);

        if ($entity === null) {
            throw BookableResourceNotFoundException::withId($id->value);
        }

        return $entity;
    }

    public function findByResource(BookableResourceId $resourceId): ?OperatingSchedule
    {
        $model = OperatingScheduleModel::query()
            ->where('resource_id', $resourceId->value)
            ->first();

        return $model !== null ? $this->toEntity($model) : null;
    }

    public function delete(OperatingScheduleId $id): void
    {
        OperatingScheduleModel::query()->where('id', $id->value)->delete();
    }

    private function toEntity(OperatingScheduleModel $model): OperatingSchedule
    {
        $daySchedules = array_map(
            fn (array $data): DaySchedule => DaySchedule::fromArray($data),
            $model->day_schedules ?? [],
        );

        return new OperatingSchedule(
            id: OperatingScheduleId::fromString($model->id),
            resourceId: BookableResourceId::fromString($model->resource_id),
            slotDurationMinutes: $model->slot_duration_minutes,
            minConsecutiveSlots: $model->min_consecutive_slots,
            maxConsecutiveSlots: $model->max_consecutive_slots,
            daySchedules: $daySchedules,
            schedulingMode: SchedulingMode::from($model->scheduling_mode ?? 'time_slots'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(OperatingSchedule $schedule): array
    {
        return [
            'id' => $schedule->id->value,
            'resource_id' => $schedule->resourceId->value,
            'scheduling_mode' => $schedule->schedulingMode->value,
            'slot_duration_minutes' => $schedule->slotDurationMinutes,
            'min_consecutive_slots' => $schedule->minConsecutiveSlots,
            'max_consecutive_slots' => $schedule->maxConsecutiveSlots,
            'day_schedules' => array_map(
                fn (DaySchedule $ds): array => $ds->toArray(),
                $schedule->daySchedules,
            ),
        ];
    }
}
