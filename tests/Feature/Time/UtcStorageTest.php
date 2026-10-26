<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Time;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Livewire\Livewire;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Console\Commands\CompletePastBookingsCommand;
use Modules\VenueBookings\Console\Commands\SendBookingRemindersCommand;
use Modules\VenueBookings\Domain\Entities\Booking;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Domain\ValueObjects\TimeRange;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\ListBookings;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;
use Modules\VenueBookings\Notifications\BookingReminderNotification;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * With UTC storage the instants in the database are UTC, while booking dates and times stay
 * wall clock of the venue and whoever reads a booking sees the site's Madrid time.
 */
final class UtcStorageTest extends ModuleTestCase
{
    use InteractsWithModulePanel;
    use UsesUtcStorage;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private ?UserModel $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        app()->setLocale('es');
        Notification::fake();

        $kernel = $this->app->make(Kernel::class);
        $kernel->registerCommand($this->app->make(SendBookingRemindersCommand::class));
        $kernel->registerCommand($this->app->make(CompletePastBookingsCommand::class));

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_every_model_stores_instants_in_the_storage_timezone(): void
    {
        $files = glob(dirname(__DIR__, 3).'/src/Infrastructure/Persistence/Eloquent/Models/*.php') ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = 'Modules\\VenueBookings\\Infrastructure\\Persistence\\Eloquent\\Models\\'.basename($file, '.php');

            $this->assertContains(StoresInstantsInStorageTimezone::class, class_uses_recursive($class), $class);
        }
    }

    public function test_the_venue_timezone_falls_back_to_the_display_timezone_not_the_storage_one(): void
    {
        config()->offsetUnset('modules.settings.venue-bookings');

        $this->assertSame('UTC', app(TimezoneServiceInterface::class)->storageTimezone()->getName());
        $this->assertSame('Europe/Madrid', app(BookingSettingsReader::class)->getDateTimeZone()->getName());
    }

    public function test_the_confirmation_and_cancellation_instants_are_stored_in_utc_and_read_back(): void
    {
        $instant = new DateTimeImmutable('2026-07-01T10:00:00Z');
        $repository = app(BookingRepositoryInterface::class);
        $booking = $this->entity('2026-07-02');
        $booking->confirmedAt = $instant;
        $booking->cancelledAt = $instant;
        $repository->save($booking);

        $row = DB::table('venuebookings_bookings')->where('id', $booking->id->value)->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-07-01 10:00:00', $row->confirmed_at);
        $this->assertSame('2026-07-01 10:00:00', $row->cancelled_at);
        $this->assertSame('2026-07-02', substr((string) $row->date, 0, 10));

        $found = $repository->findOrFail($booking->id);
        $this->assertEquals($instant->getTimestamp(), $found->confirmedAt?->getTimestamp());
        $this->assertEquals($instant->getTimestamp(), $found->cancelledAt?->getTimestamp());
    }

    public function test_the_serialized_instants_carry_the_display_offset(): void
    {
        $instant = new DateTimeImmutable('2026-07-01T10:00:00Z');
        $booking = $this->entity('2026-07-02');
        $booking->confirmedAt = $instant;
        $booking->cancelledAt = $instant;

        $array = BookingResponseDTO::fromEntity($booking)->toArray();

        $this->assertSame('2026-07-01T12:00:00+02:00', $array['confirmed_at']);
        $this->assertSame('2026-07-01T12:00:00+02:00', $array['cancelled_at']);
        $this->assertNull(BookingResponseDTO::fromEntity($this->entity('2026-07-02'))->toArray()['confirmed_at']);
    }

    public function test_the_panel_list_shows_the_creation_time_in_madrid(): void
    {
        $this->registerOnAdminPanel([BookingResource::class]);
        $admin = $this->actingAsPanelAdmin();
        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $admin->id,
            'date' => '2026-07-02',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'status' => 'pending',
        ]);
        $booking->created_at = Carbon::parse('2026-07-01T10:00:00Z');
        $booking->save();

        Livewire::test(ListBookings::class)
            ->assertTableColumnFormattedStateSet('created_at', '01/07/2026 12:00', $booking);
    }

    public function test_a_reminder_for_ten_local_on_the_day_the_clocks_go_back_fires_at_the_right_instant(): void
    {
        config()->set('modules.settings.venue-bookings.notifications.notify_on_booking_reminder', true);
        config()->set('modules.settings.venue-bookings.notifications.reminder_hours_before', 24);
        // 10:00 on 2026-10-25 is CET (UTC+1), that is 09:00Z
        $booking = $this->confirmedBooking('2026-10-25', '10:00', '12:00');

        // 08:30Z: the window ends at 08:30Z on the 25th, before the booking starts
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-24T08:30:00Z'));
        $this->runCommand('venue-bookings:send-reminders');
        Notification::assertNothingSent();

        // 09:30Z: the window reaches 09:30Z on the 25th, past the 09:00Z start
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-24T09:30:00Z'));
        $this->runCommand('venue-bookings:send-reminders');

        Notification::assertSentToTimes($this->user(), BookingReminderNotification::class, 1);
        $this->assertSame(
            '2026-10-24 09:30:00',
            DB::table('venuebookings_bookings')->where('id', $booking->id)->value('reminder_sent_at'),
        );
    }

    public function test_a_booking_is_completed_once_its_end_in_the_venue_zone_has_passed(): void
    {
        config()->set('modules.settings.venue-bookings.auto_complete_enabled', true);
        config()->set('modules.settings.venue-bookings.auto_complete_after_hours', 0);
        // Ends 12:00 CET on 2026-10-25, that is 11:00Z
        $booking = $this->confirmedBooking('2026-10-25', '10:00', '12:00');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25T10:30:00Z'));
        $this->runCommand('venue-bookings:complete-past');
        $this->assertSame(BookingStatus::Confirmed, $this->statusOf($booking));

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-25T11:30:00Z'));
        $this->runCommand('venue-bookings:complete-past');
        $this->assertSame(BookingStatus::Completed, $this->statusOf($booking));
    }

    private function statusOf(BookingModel $booking): BookingStatus
    {
        return BookingModel::query()->findOrFail($booking->id)->status;
    }

    private function runCommand(string $name): void
    {
        $command = $this->artisan($name);
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();
    }

    private function user(): UserModel
    {
        return $this->user ??= UserModel::factory()->create();
    }

    private function confirmedBooking(string $date, string $start, string $end): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $this->user()->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'confirmed',
        ]);
    }

    private function entity(string $date): Booking
    {
        return new Booking(
            id: BookingId::generate(),
            resourceId: BookableResourceId::fromString($this->room->id),
            userId: $this->user()->id,
            date: $date,
            timeRange: new TimeRange('18:00', '20:00'),
            status: BookingStatus::Confirmed,
        );
    }
}
