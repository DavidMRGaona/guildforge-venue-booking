<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Repositories;

use Modules\VenueBookings\Domain\Entities\OperatingSchedule;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\OperatingScheduleId;

interface OperatingScheduleRepositoryInterface
{
    public function save(OperatingSchedule $schedule): void;

    public function find(OperatingScheduleId $id): ?OperatingSchedule;

    public function findOrFail(OperatingScheduleId $id): OperatingSchedule;

    public function findByResource(BookableResourceId $resourceId): ?OperatingSchedule;

    public function delete(OperatingScheduleId $id): void;
}
