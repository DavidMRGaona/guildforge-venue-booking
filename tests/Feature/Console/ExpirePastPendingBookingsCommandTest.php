<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Mockery\MockInterface;
use Modules\VenueBookings\Console\Commands\ExpirePastPendingBookingsCommand;
use Modules\VenueBookings\Domain\Events\BookingExpired;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

final class ExpirePastPendingBookingsCommandTest extends ModuleTestCase
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
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ExpirePastPendingBookingsCommand::class));

        Notification::fake();
        Mail::fake();

        config()->set('modules.settings.venue-bookings.timezone', self::VENUE_TIMEZONE);
        // Expiring does not depend on the automatic completion of confirmed bookings
        config()->set('modules.settings.venue-bookings.auto_complete_enabled', false);

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

    public function test_a_pending_booking_expires_once_it_has_ended(): void
    {
        $endedNow = $this->booking('2026-11-12', '08:00', '10:00');
        $endedLongAgo = $this->booking('2026-10-01', '18:00', '20:00');
        $underWay = $this->booking('2026-11-12', '09:00', '11:00');
        $future = $this->booking('2026-11-13', '10:00', '12:00');

        $this->expirePast('Expired 2 booking(s).');

        $this->assertSame('expired', $endedNow->fresh()?->status->value);
        $this->assertSame('expired', $endedLongAgo->fresh()?->status->value);
        $this->assertSame('pending', $underWay->fresh()?->status->value);
        $this->assertSame('pending', $future->fresh()?->status->value);
    }

    public function test_a_booking_that_crosses_midnight_expires_from_its_real_end(): void
    {
        $ended = $this->booking('2026-11-11', '22:00', '02:00');    // ended 11-12 02:00
        $underWay = $this->booking('2026-11-11', '22:00', '11:00'); // ends 11-12 11:00

        $this->expirePast('Expired 1 booking(s).');

        $this->assertSame('expired', $ended->fresh()?->status->value);
        $this->assertSame('pending', $underWay->fresh()?->status->value);
    }

    public function test_bookings_that_are_not_pending_are_left_alone(): void
    {
        $others = [];
        foreach (['confirmed', 'cancelled', 'rejected', 'no_show', 'completed'] as $status) {
            $others[$status] = $this->booking('2026-11-01', '10:00', '12:00', $status);
        }

        $this->expirePast('Expired 0 booking(s).');

        foreach ($others as $status => $booking) {
            $this->assertSame($status, $booking->fresh()?->status->value);
        }
    }

    public function test_expiring_sends_no_email_and_announces_the_event(): void
    {
        Event::fake([BookingExpired::class]);
        $booking = $this->booking('2026-11-01', '10:00', '12:00');

        $this->expirePast('Expired 1 booking(s).');

        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Event::assertDispatchedTimes(BookingExpired::class, 1);
        Event::assertDispatched(
            BookingExpired::class,
            fn (BookingExpired $event): bool => $event->bookingId === $booking->id && $event->userId === $this->user->id,
        );
    }

    public function test_a_booking_approved_after_the_query_is_skipped(): void
    {
        $booking = $this->booking('2026-11-01', '10:00', '12:00');
        $real = $this->app->make(BookingRepositoryInterface::class);
        $stale = $real->find(BookingId::fromString($booking->id));
        $this->assertNotNull($stale);

        // Staff confirm it between the query and the update
        $booking->update(['status' => 'confirmed']);

        $this->mock(BookingRepositoryInterface::class, function (MockInterface $mock) use ($real, $stale): void {
            $mock->shouldReceive('getPendingEndedBy')->andReturn([$stale]);
            $mock->shouldReceive('find')->andReturnUsing(fn (BookingId $id) => $real->find($id));
            $mock->shouldReceive('save')->never();
        });

        $this->expirePast('Expired 0 booking(s).');

        $this->assertSame('confirmed', $booking->fresh()?->status->value);
    }

    public function test_a_failing_booking_is_logged_and_does_not_stop_the_others(): void
    {
        $log = Log::spy();
        $broken = $this->booking('2026-11-01', '10:00', '12:00');
        $fine = $this->booking('2026-11-02', '10:00', '12:00');
        $real = $this->app->make(BookingRepositoryInterface::class);

        $this->mock(BookingRepositoryInterface::class, function (MockInterface $mock) use ($real, $broken): void {
            $mock->shouldReceive('getPendingEndedBy')->andReturnUsing(fn (\DateTimeInterface $endedBy): array => $real->getPendingEndedBy($endedBy));
            $mock->shouldReceive('find')->andReturnUsing(fn (BookingId $id) => $real->find($id));
            $mock->shouldReceive('findOrFail')->andReturnUsing(fn (BookingId $id) => $real->findOrFail($id));
            $mock->shouldReceive('save')->andReturnUsing(function ($booking) use ($real, $broken): void {
                if ($booking->id->value === $broken->id) {
                    throw new RuntimeException('Simulated failure');
                }

                $real->save($booking);
            });
        });

        $this->expirePast('Expired 1 booking(s).');

        $this->assertSame('pending', $broken->fresh()?->status->value);
        $this->assertSame('expired', $fine->fresh()?->status->value);
        $log->shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []): bool => ($context['booking_id'] ?? null) === $broken->id
                && ($context['exception'] ?? null) instanceof RuntimeException)
            ->once();
    }

    public function test_the_command_is_scheduled_hourly_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'venue-bookings:expire-past-pending'),
        ));

        $this->assertNotEmpty($events);
        $this->assertSame('0 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        $this->assertSame(10, $events[0]->expiresAt);
    }

    private function expirePast(string $expectedOutput): void
    {
        $command = $this->artisan('venue-bookings:expire-past-pending');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->expectsOutput($expectedOutput)->assertSuccessful()->run();
    }

    private function booking(string $date, string $startTime, string $endTime, string $status = 'pending'): BookingModel
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
