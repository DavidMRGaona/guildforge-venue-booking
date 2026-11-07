<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\DTOs\MoveBookingDTO;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Notifications\BookingMovedNotification;
use Modules\VenueBookings\Tests\Support\OpensResourceEveryDay;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Moving an active booking in the panel tells whoever made it where and when it is now.
 */
final class BookingMovedEmailTest extends ModuleTestCase
{
    use OpensResourceEveryDay;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private BookableResourceModel $hall;

    private UserModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        Notification::fake();
        config()->set('modules.settings.venue-bookings.timezone', 'Europe/Madrid');
        $this->travelTo(CarbonImmutable::create(2026, 3, 1, 9, 0, 0, 'Europe/Madrid'));

        $this->room = $this->createResource('Sala grande', 'sala-grande');
        $this->hall = $this->createResource('Sala pequeña', 'sala-pequena');
        $this->member = UserModel::factory()->create(['name' => 'John']);
    }

    public function test_the_owner_is_told_the_new_date_and_time_once(): void
    {
        $booking = $this->bookingOf('confirmed');

        $this->move($booking, $this->room, '2026-03-17', '14:00', '16:00');

        Notification::assertSentToTimes($this->member, BookingMovedNotification::class, 1);
        Notification::assertSentTo($this->member, BookingMovedNotification::class, function (BookingMovedNotification $notification): bool {
            $message = $notification->toMail($this->member);

            return $message->subject === 'Reserva modificada: Sala grande, 17/03/2026'
                && $message->greeting === '¡Hola, John!'
                && $message->introLines === [
                    'Hemos cambiado tu reserva. Estos son los datos nuevos:',
                    '**Espacio:** Sala grande',
                    '**Fecha:** 17/03/2026',
                    '**Horario:** 14:00 - 16:00',
                    '**Antes:** Sala grande, 16/03/2026, 10:00 - 12:00',
                ]
                && $message->actionText === 'Ver mis reservas'
                && $notification->afterCommit === true;
        });
    }

    public function test_a_pending_booking_moved_is_told_too(): void
    {
        $booking = $this->bookingOf('pending');

        $this->move($booking, $this->room, '2026-03-16', '11:00', '13:00');

        Notification::assertSentToTimes($this->member, BookingMovedNotification::class, 1);
    }

    public function test_a_change_of_space_alone_is_told_with_the_new_space(): void
    {
        $booking = $this->bookingOf('confirmed');

        $this->move($booking, $this->hall, '2026-03-16', '10:00', '12:00');

        Notification::assertSentTo($this->member, BookingMovedNotification::class, function (BookingMovedNotification $notification): bool {
            $lines = $notification->toMail($this->member)->introLines;

            return in_array('**Espacio:** Sala pequeña', $lines, true)
                && in_array('**Antes:** Sala grande, 16/03/2026, 10:00 - 12:00', $lines, true);
        });
    }

    public function test_nothing_is_sent_when_the_setting_is_off(): void
    {
        config()->set('modules.settings.venue-bookings.notifications.notify_on_booking_moved', false);
        $booking = $this->bookingOf('confirmed');

        $this->move($booking, $this->room, '2026-03-17', '10:00', '12:00');

        Notification::assertNothingSent();
    }

    public function test_nothing_is_sent_when_the_staff_choose_not_to_tell(): void
    {
        $booking = $this->bookingOf('confirmed');

        $this->move($booking, $this->room, '2026-03-17', '10:00', '12:00', notifyOwner: false);

        Notification::assertNothingSent();
    }

    public function test_the_email_goes_out_only_once_the_transaction_commits(): void
    {
        $booking = $this->bookingOf('confirmed');

        DB::transaction(function () use ($booking): void {
            $this->move($booking, $this->room, '2026-03-17', '10:00', '12:00');

            Notification::assertNothingSent();
        });

        Notification::assertSentToTimes($this->member, BookingMovedNotification::class, 1);
    }

    public function test_a_rolled_back_move_tells_nobody(): void
    {
        $booking = $this->bookingOf('confirmed');

        try {
            DB::transaction(function () use ($booking): void {
                $this->move($booking, $this->room, '2026-03-17', '10:00', '12:00');

                throw new \RuntimeException('Roll back');
            });
        } catch (\RuntimeException) {
        }

        Notification::assertNothingSent();
    }

    private function move(string $bookingId, BookableResourceModel $resource, string $date, string $start, string $end, bool $notifyOwner = true): void
    {
        app(BookingServiceInterface::class)->moveBookingAsStaff(new MoveBookingDTO(
            bookingId: $bookingId,
            resourceId: $resource->id,
            date: $date,
            startTime: $start,
            endTime: $end,
            notifyOwner: $notifyOwner,
        ));
    }

    private function bookingOf(string $status): string
    {
        $id = (string) Str::uuid();
        BookingModel::create([
            'id' => $id,
            'resource_id' => $this->room->id,
            'user_id' => $this->member->id,
            'date' => '2026-03-16',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => $status,
        ]);

        return $id;
    }

    private function createResource(string $name, string $slug): BookableResourceModel
    {
        $resource = BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'sort_order' => 0,
        ]);
        $this->openEveryDay($resource->id, '08:00', '22:00');

        return $resource;
    }
}
