<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Mockery\MockInterface;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Console\Commands\CompletePastBookingsCommand;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

final class CompletePastBookingsCommandTest extends ModuleTestCase
{
    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The module is booted after the console application exists, so its
        // commands have to be added to it by hand.
        $this->app->make(Kernel::class)->registerCommand($this->app->make(CompletePastBookingsCommand::class));

        Notification::fake();
        Mail::fake();

        config()->set('modules.settings.venue-bookings.timezone', self::VENUE_TIMEZONE);
        config()->set('modules.settings.venue-bookings.auto_complete_enabled', true);
        config()->set('modules.settings.venue-bookings.auto_complete_after_hours', 24);

        // Thursday 2026-11-12, 10:00 at the venue
        $this->travelTo(CarbonImmutable::create(2026, 11, 12, 10, 0, 0, self::VENUE_TIMEZONE));

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();
    }

    public function test_a_confirmed_booking_is_completed_once_its_end_plus_the_margin_has_passed(): void
    {
        $due = $this->booking('2026-11-11', '08:00', '10:00');      // ended 24 h ago exactly
        $notYet = $this->booking('2026-11-11', '09:00', '10:30');   // 24 h after its end is 10:30

        $this->completePast('Completed 1 booking(s).');

        $this->assertSame('completed', $due->fresh()?->status->value);
        $this->assertSame('confirmed', $notYet->fresh()?->status->value);
    }

    public function test_with_no_margin_a_booking_is_completed_when_it_ends(): void
    {
        config()->set('modules.settings.venue-bookings.auto_complete_after_hours', 0);
        $ended = $this->booking('2026-11-12', '08:00', '10:00');
        $running = $this->booking('2026-11-12', '09:00', '11:00');

        $this->completePast('Completed 1 booking(s).');

        $this->assertSame('completed', $ended->fresh()?->status->value);
        $this->assertSame('confirmed', $running->fresh()?->status->value);
    }

    public function test_a_booking_that_crosses_midnight_is_completed_from_its_real_end(): void
    {
        $endedYesterday = $this->booking('2026-11-10', '22:00', '02:00');  // ends 11-11 02:00
        $endedToday = $this->booking('2026-11-11', '22:00', '02:00');      // ends 11-12 02:00

        $this->completePast('Completed 1 booking(s).');

        $this->assertSame('completed', $endedYesterday->fresh()?->status->value);
        $this->assertSame('confirmed', $endedToday->fresh()?->status->value);
    }

    public function test_bookings_that_are_not_confirmed_are_left_alone(): void
    {
        $others = [];
        foreach (['pending', 'cancelled', 'rejected', 'no_show', 'completed'] as $status) {
            $others[$status] = $this->booking('2026-11-01', '10:00', '12:00', $status);
        }

        $this->completePast('Completed 0 booking(s).');

        foreach ($others as $status => $booking) {
            $this->assertSame($status, $booking->fresh()?->status->value);
        }
    }

    public function test_nothing_changes_when_auto_completion_is_off(): void
    {
        config()->set('modules.settings.venue-bookings.auto_complete_enabled', false);
        $due = $this->booking('2026-11-01', '10:00', '12:00');

        $this->completePast('Automatic completion is disabled.');

        $this->assertSame('confirmed', $due->fresh()?->status->value);
    }

    public function test_completing_sends_no_email(): void
    {
        $this->booking('2026-11-01', '10:00', '12:00');

        $this->completePast('Completed 1 booking(s).');

        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_a_booking_changed_by_hand_after_the_query_is_skipped(): void
    {
        $booking = $this->booking('2026-11-01', '10:00', '12:00');
        $real = $this->app->make(BookingRepositoryInterface::class);
        $stale = $real->find(BookingId::fromString($booking->id));
        $this->assertNotNull($stale);

        // Staff cancel it between the query and the update
        $booking->update(['status' => 'cancelled']);

        $this->mock(BookingRepositoryInterface::class, function (MockInterface $mock) use ($real, $stale): void {
            $mock->shouldReceive('getConfirmedEndedBy')->andReturn([$stale]);
            $mock->shouldReceive('find')->andReturnUsing(fn (BookingId $id) => $real->find($id));
        });

        $this->completePast('Completed 0 booking(s).');

        $this->assertSame('cancelled', $booking->fresh()?->status->value);
    }

    public function test_a_failing_booking_is_logged_and_does_not_stop_the_others(): void
    {
        Log::spy();
        $broken = $this->booking('2026-11-01', '10:00', '12:00');
        $fine = $this->booking('2026-11-02', '10:00', '12:00');
        $real = $this->app->make(BookingServiceInterface::class);

        $this->mock(BookingServiceInterface::class, function (MockInterface $mock) use ($real, $broken): void {
            $mock->shouldReceive('markCompleted')->andReturnUsing(
                function (string $id) use ($real, $broken): BookingResponseDTO {
                    if ($id === $broken->id) {
                        throw new RuntimeException('Simulated failure');
                    }

                    return $real->markCompleted($id);
                },
            );
        });

        $this->completePast('Completed 1 booking(s).');

        $this->assertSame('confirmed', $broken->fresh()?->status->value);
        $this->assertSame('completed', $fine->fresh()?->status->value);
        // The exception itself goes in the context, so the log keeps its class and stack trace
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []): bool => ($context['booking_id'] ?? null) === $broken->id
                && ($context['exception'] ?? null) instanceof RuntimeException
                && $context['exception']->getMessage() === 'Simulated failure')
            ->once();
    }

    public function test_the_command_is_scheduled_hourly_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'venue-bookings:complete-past'),
        ));

        $this->assertNotEmpty($events);
        $this->assertSame('0 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertSame(10, $events[0]->expiresAt);
    }

    private function completePast(string $expectedOutput): void
    {
        $command = $this->artisan('venue-bookings:complete-past');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput($expectedOutput)->assertSuccessful()->run();
    }

    private function booking(string $date, string $startTime, string $endTime, string $status = 'confirmed'): BookingModel
    {
        return BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
        ]);
    }
}
