<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Console;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Modules\VenueBookings\Console\Commands\SendBookingRemindersCommand;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Notifications\BookingReminderNotification;
use Tests\Support\Modules\ModuleTestCase;

final class SendBookingRemindersCommandTest extends ModuleTestCase
{
    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The module is booted after the console application exists, so its
        // commands have to be added to it by hand.
        $this->app->make(Kernel::class)->registerCommand($this->app->make(SendBookingRemindersCommand::class));

        Notification::fake();

        config()->set('modules.settings.venue-bookings.timezone', 'Europe/Madrid');
        config()->set('modules.settings.venue-bookings.notifications.notify_on_booking_reminder', true);
        config()->set('modules.settings.venue-bookings.notifications.reminder_hours_before', 24);

        // 10:00 in the venue's timezone
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 10:00:00', 'Europe/Madrid'));

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        $this->user = UserModel::factory()->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_a_booking_starting_within_the_window_is_reminded_once(): void
    {
        $booking = $this->createConfirmedBooking('2026-11-11', '09:00', '11:00');

        $this->sendReminders();
        $this->sendReminders();

        Notification::assertSentToTimes($this->user, BookingReminderNotification::class, 1);
        $this->assertNotNull($booking->fresh()?->getAttribute('reminder_sent_at'));
    }

    public function test_a_booking_that_already_started_is_not_reminded(): void
    {
        $this->createConfirmedBooking('2026-11-10', '09:00', '12:00');

        $this->sendReminders();

        Notification::assertNothingSent();
    }

    public function test_a_booking_beyond_the_window_is_not_reminded(): void
    {
        $this->createConfirmedBooking('2026-11-11', '11:00', '13:00');

        $this->sendReminders();

        Notification::assertNothingSent();
    }

    public function test_a_booking_that_is_not_confirmed_is_not_reminded(): void
    {
        $this->createConfirmedBooking('2026-11-10', '18:00', '20:00', status: 'pending');

        $this->sendReminders();

        Notification::assertNothingSent();
    }

    public function test_nothing_is_sent_when_reminders_are_off_in_the_panel(): void
    {
        config()->set('modules.settings.venue-bookings.notifications.notify_on_booking_reminder', false);
        $this->createConfirmedBooking('2026-11-10', '18:00', '20:00');

        $this->sendReminders();

        Notification::assertNothingSent();
    }

    public function test_the_reminder_command_is_scheduled_hourly_without_overlapping(): void
    {
        $events = array_values(array_filter(
            app(Schedule::class)->events(),
            fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'venue-bookings:send-reminders'),
        ));

        $this->assertNotEmpty($events);
        $this->assertSame('0 * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
        // A killed run must not block reminders for long.
        $this->assertSame(10, $events[0]->expiresAt);
    }

    private function sendReminders(): void
    {
        $command = $this->artisan('venue-bookings:send-reminders');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();
    }

    private function createConfirmedBooking(
        string $date,
        string $startTime,
        string $endTime,
        string $status = 'confirmed',
    ): BookingModel {
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
