<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Database;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Modules\VenueBookings\Database\Seeders\DevelopmentSeeder;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Tests\Support\Modules\ModuleTestCase;

final class DevelopmentSeederTest extends ModuleTestCase
{
    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private function runSeeder(): void
    {
        require_once dirname(__DIR__, 3).'/database/seeders/DevelopmentSeeder.php';

        (new DevelopmentSeeder)->setContainer($this->app)->run();
    }

    public function test_it_writes_nothing_in_production(): void
    {
        // Enabling the module in testing already ran the seeder: empty its tables first
        BookingModel::query()->delete();
        BookableResourceModel::query()->delete();
        UserModel::where('email', 'like', '%@example.local')->forceDelete();

        $this->app['env'] = 'production';
        $this->assertTrue($this->app->environment('production'));

        $this->runSeeder();

        $this->assertSame(0, BookableResourceModel::count());
        $this->assertSame(0, BookingModel::count());
        $this->assertSame(0, UserModel::where('email', 'like', '%@example.local')->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->app['env'] = 'local';
        $this->runSeeder();
        $counts = [BookableResourceModel::count(), BookingModel::count(), UserModel::count()];

        $this->runSeeder();

        $this->assertSame($counts, [BookableResourceModel::count(), BookingModel::count(), UserModel::count()]);
        $this->assertGreaterThan(0, $counts[1]);
    }

    public function test_it_seeds_one_booking_of_every_status_inside_the_schedule_without_notifying(): void
    {
        $this->app['env'] = 'local';
        Mail::fake();
        Notification::fake();

        $this->runSeeder();

        foreach (BookingStatus::cases() as $status) {
            $this->assertGreaterThan(0, BookingModel::where('status', $status->value)->count(), $status->value);
        }

        $today = now()->toDateString();
        $this->assertTrue(BookingModel::where('status', BookingStatus::Completed->value)->get()->every(fn (BookingModel $b): bool => $b->date->toDateString() < $today));
        $this->assertTrue(BookingModel::whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])->get()->every(fn (BookingModel $b): bool => $b->date->toDateString() >= $today));
        $this->assertNotNull(BookingModel::where('status', BookingStatus::Cancelled->value)->firstOrFail()->cancelled_at);

        foreach (BookingModel::with('resource.operatingSchedule')->get() as $booking) {
            $schedule = $booking->resource->operatingSchedule;
            $this->assertNotNull($schedule);
            $day = collect($schedule->day_schedules)->firstWhere('day_of_week', $booking->date->dayOfWeek);
            $this->assertNotNull($day);
            $this->assertTrue($day['is_enabled']);
            $this->assertGreaterThanOrEqual($day['open_time'], substr($booking->start_time, 0, 5));
            $this->assertLessThanOrEqual($day['close_time'], substr($booking->end_time, 0, 5));
        }

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }
}
