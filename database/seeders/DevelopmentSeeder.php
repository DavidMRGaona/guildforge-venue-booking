<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->seedBookableResources();
    }

    private function seedBookableResources(): void
    {
        $resources = [
            [
                'name' => 'Sala principal',
                'slug' => 'sala-principal',
                'description' => 'Sala principal de la asociación. Amplio espacio con mesas modulares, ideal para partidas de rol y wargames.',
                'capacity' => 20,
                'status' => BookableResourceStatus::Active,
                'sort_order' => 0,
                'schedule' => [
                    'slot_duration_minutes' => 60,
                    'min_consecutive_slots' => 1,
                    'max_consecutive_slots' => 4,
                    'day_schedules' => [
                        ['day_of_week' => 0, 'open_time' => '10:00', 'close_time' => '20:00', 'is_enabled' => true],  // Sunday
                        ['day_of_week' => 1, 'open_time' => '17:00', 'close_time' => '22:00', 'is_enabled' => true],  // Monday
                        ['day_of_week' => 2, 'open_time' => '17:00', 'close_time' => '22:00', 'is_enabled' => true],  // Tuesday
                        ['day_of_week' => 3, 'open_time' => '17:00', 'close_time' => '22:00', 'is_enabled' => true],  // Wednesday
                        ['day_of_week' => 4, 'open_time' => '17:00', 'close_time' => '22:00', 'is_enabled' => true],  // Thursday
                        ['day_of_week' => 5, 'open_time' => '17:00', 'close_time' => '22:00', 'is_enabled' => true],  // Friday
                        ['day_of_week' => 6, 'open_time' => '10:00', 'close_time' => '22:00', 'is_enabled' => true],  // Saturday
                    ],
                ],
            ],
            [
                'name' => 'Sala de pintura',
                'slug' => 'sala-de-pintura',
                'description' => 'Sala dedicada al montaje y pintura de miniaturas. Equipada con aerógrafos, luces de trabajo y estanterías.',
                'capacity' => 8,
                'status' => BookableResourceStatus::Active,
                'sort_order' => 1,
                'schedule' => [
                    'slot_duration_minutes' => 120,
                    'min_consecutive_slots' => 1,
                    'max_consecutive_slots' => 2,
                    'day_schedules' => [
                        ['day_of_week' => 0, 'open_time' => '10:00', 'close_time' => '20:00', 'is_enabled' => true],
                        ['day_of_week' => 1, 'open_time' => '17:00', 'close_time' => '21:00', 'is_enabled' => false],
                        ['day_of_week' => 2, 'open_time' => '17:00', 'close_time' => '21:00', 'is_enabled' => true],
                        ['day_of_week' => 3, 'open_time' => '17:00', 'close_time' => '21:00', 'is_enabled' => false],
                        ['day_of_week' => 4, 'open_time' => '17:00', 'close_time' => '21:00', 'is_enabled' => true],
                        ['day_of_week' => 5, 'open_time' => '17:00', 'close_time' => '21:00', 'is_enabled' => true],
                        ['day_of_week' => 6, 'open_time' => '10:00', 'close_time' => '20:00', 'is_enabled' => true],
                    ],
                ],
            ],
        ];

        foreach ($resources as $resourceData) {
            $schedule = $resourceData['schedule'];
            unset($resourceData['schedule']);

            $resource = BookableResourceModel::firstOrCreate(
                ['slug' => $resourceData['slug']],
                array_merge($resourceData, ['id' => Str::uuid()->toString()]),
            );

            OperatingScheduleModel::firstOrCreate(
                ['resource_id' => $resource->id],
                array_merge($schedule, ['id' => Str::uuid()->toString()]),
            );
        }
    }
}
