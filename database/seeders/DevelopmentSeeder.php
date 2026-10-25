<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Database\Seeders;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\OperatingScheduleModel;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data: the core runs every module seeder on install and update, production included
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->seedBookableResources();

        // Enabling the module in tests runs this seeder too, and the suites expect no bookings
        if (app()->environment('local')) {
            $this->seedBookings();
        }
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

    /**
     * Bookings around this week and next, one per status, written straight to the table so
     * no confirmation, cancellation or reminder email is sent. Times follow each resource
     * schedule: hourly slots on 18:00-20:00 for the main room, the first two-hour slots of the
     * day for the painting room.
     */
    private function seedBookings(): void
    {
        $people = $this->samplePeople();
        $principal = BookableResourceModel::where('slug', 'sala-principal')->first();
        $painting = BookableResourceModel::where('slug', 'sala-de-pintura')->first();

        if ($principal === null || $painting === null) {
            return;
        }

        // [resource, person, status, day offset, first slot start or null for the day's opening, slots, reason]
        $bookings = [
            [$principal, 'john', BookingStatus::Completed, -3, '18:00', 2, null],
            [$principal, 'mary', BookingStatus::NoShow, -1, '18:00', 1, null],
            [$principal, 'jane', BookingStatus::Confirmed, 1, '18:00', 2, null],
            [$principal, 'richard', BookingStatus::Pending, 2, '18:00', 2, null],
            [$principal, 'mary', BookingStatus::Cancelled, 4, '18:00', 1, 'Nos surge un imprevisto y no podemos venir.'],
            [$principal, 'john', BookingStatus::Rejected, 5, '18:00', 2, 'Ese día la sala está reservada para una actividad de la asociación.'],
            [$principal, 'richard', BookingStatus::Confirmed, 8, '18:00', 2, null],
            [$principal, 'john', BookingStatus::Pending, 9, '18:00', 1, null],
            [$painting, 'jane', BookingStatus::Completed, -2, null, 1, null],
            [$painting, 'richard', BookingStatus::Confirmed, 3, null, 1, null],
            [$painting, 'mary', BookingStatus::Pending, 7, null, 2, null],
        ];

        foreach ($bookings as [$resource, $person, $status, $offset, $startTime, $slots, $reason]) {
            $schedule = $resource->operatingSchedule;

            if ($schedule === null) {
                continue;
            }

            $slotMinutes = (int) $schedule->slot_duration_minutes;
            $date = $this->nearestOpenDate($schedule->day_schedules, $offset);
            $dayOpening = $this->dayOpening($schedule->day_schedules, $date);
            $start = $startTime ?? $dayOpening;
            $end = date('H:i', (int) strtotime($start) + $slotMinutes * $slots * 60);

            $happened = in_array($status, [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow], true);

            BookingModel::updateOrCreate(
                [
                    'resource_id' => $resource->id,
                    'user_id' => $people[$person]->id,
                    'status' => $status->value,
                ],
                [
                    'date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'cancellation_reason' => $reason,
                    'confirmed_at' => $happened ? now()->subDays(4) : null,
                    'cancelled_at' => $status === BookingStatus::Cancelled ? now()->subDay() : null,
                ],
            );
        }
    }

    /**
     * The date closest to today plus the offset on which the resource is open, moving
     * through disabled days away from today, so a past booking stays in the past.
     *
     * @param  array<int, array<string, mixed>>  $daySchedules
     */
    private function nearestOpenDate(array $daySchedules, int $offset): string
    {
        $date = now()->addDays($offset);

        for ($tries = 0; $tries < 7; $tries++) {
            $schedule = $this->scheduleOf($daySchedules, $date->dayOfWeek);

            if ($schedule !== null && $schedule['is_enabled']) {
                break;
            }

            $date = $offset < 0 ? $date->subDay() : $date->addDay();
        }

        return $date->toDateString();
    }

    /**
     * @param  array<int, array<string, mixed>>  $daySchedules
     */
    private function dayOpening(array $daySchedules, string $date): string
    {
        $schedule = $this->scheduleOf($daySchedules, Carbon::parse($date)->dayOfWeek);

        return (string) ($schedule['open_time'] ?? '10:00');
    }

    /**
     * @param  array<int, array<string, mixed>>  $daySchedules
     * @return array<string, mixed>|null
     */
    private function scheduleOf(array $daySchedules, int $dayOfWeek): ?array
    {
        foreach ($daySchedules as $schedule) {
            if ((int) $schedule['day_of_week'] === $dayOfWeek) {
                return $schedule;
            }
        }

        return null;
    }

    /**
     * @return array<string, UserModel>
     */
    private function samplePeople(): array
    {
        $memberRole = app(MemberRoleServiceInterface::class);
        $people = [];

        foreach (['john' => 'John Doe', 'jane' => 'Jane Doe', 'richard' => 'Richard Roe', 'mary' => 'Mary Major'] as $key => $name) {
            $email = Str::lower(str_replace(' ', '.', $name)).'@example.local';
            $user = UserModel::where('email', $email)->first();

            if ($user === null) {
                $user = new UserModel;
                $user->forceFill([
                    'name' => $name,
                    'display_name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ])->save();
            }

            $memberRole->assignTo($user);
            $people[$key] = $user;
        }

        return $people;
    }
}
