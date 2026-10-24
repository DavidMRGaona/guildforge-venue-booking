<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Notifications\BookingConfirmedNotification;
use Modules\VenueBookings\Notifications\BookingCreatedNotification;
use Modules\VenueBookings\Notifications\BookingReceivedNotification;
use Modules\VenueBookings\Notifications\BookingReminderNotification;
use Modules\VenueBookings\Tests\Support\OpensResourceEveryDay;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Who hears about a new booking: the people who manage bookings (by permission, not only
 * the admin role), and the member who booked when the booking waits for approval.
 */
final class BookingEmailsTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use OpensResourceEveryDay;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        Notification::fake();
        config()->set('modules.settings.venue-bookings.timezone', 'Europe/Madrid');
        $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'Europe/Madrid'));

        $this->room = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $this->openEveryDay($this->room->id, '08:00', '22:00');
        $this->member = UserModel::factory()->create(['name' => 'John']);
    }

    public function test_a_new_booking_is_announced_to_admins_and_to_whoever_manages_bookings(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $manager = $this->editorWithPermissions(['venuebookings:bookings.manage']);
        $other = $this->editorWithPermissions(['venuebookings:bookings.view_any']);

        $this->book();

        Notification::assertSentTo($admin, BookingCreatedNotification::class);
        Notification::assertSentTo($manager, BookingCreatedNotification::class);
        Notification::assertNotSentTo($other, BookingCreatedNotification::class);
        Notification::assertNotSentTo($this->member, BookingCreatedNotification::class);
    }

    public function test_a_booking_waiting_for_approval_tells_the_member_it_was_received(): void
    {
        config()->set('modules.settings.venue-bookings.approval_mode', 'require_approval');

        $this->book();

        Notification::assertSentTo($this->member, BookingReceivedNotification::class, function (BookingReceivedNotification $n): bool {
            $message = $n->toMail($this->member);

            return $message->subject === 'Reserva recibida: Sala grande, 16/03/2026'
                && str_contains(implode(' ', $message->introLines), 'pendiente de aprobación');
        });
        Notification::assertNotSentTo($this->member, BookingConfirmedNotification::class);
    }

    public function test_an_automatically_confirmed_booking_only_sends_the_confirmation(): void
    {
        config()->set('modules.settings.venue-bookings.approval_mode', 'auto_confirm');

        $this->book();

        Notification::assertSentTo($this->member, BookingConfirmedNotification::class);
        Notification::assertNotSentTo($this->member, BookingReceivedNotification::class);
    }

    public function test_the_received_email_can_be_turned_off(): void
    {
        config()->set('modules.settings.venue-bookings.approval_mode', 'require_approval');
        config()->set('modules.settings.venue-bookings.notifications.notify_on_booking_received', false);

        $this->book();

        Notification::assertNotSentTo($this->member, BookingReceivedNotification::class);
    }

    public function test_the_reminder_names_the_day_instead_of_saying_tomorrow(): void
    {
        $message = (new BookingReminderNotification('id', 'John', 'Sala grande', '2026-03-16', '10:00', '12:00'))->toMail($this->member);

        $this->assertSame('Recordatorio: tu reserva en Sala grande, 16/03/2026', $message->subject);
        $this->assertStringNotContainsString('mañana', (string) $message->subject);
        $this->assertSame('¡Hola, John!', $message->greeting);
        $this->assertContains('**Fecha:** 16/03/2026', $message->introLines);
    }

    private function book(): void
    {
        app(BookingServiceInterface::class)->createBooking(new CreateBookingDTO(
            resourceId: $this->room->id,
            userId: $this->member->id,
            date: '2026-03-16',
            startTime: '10:00',
            endTime: '12:00',
        ));
    }
}
