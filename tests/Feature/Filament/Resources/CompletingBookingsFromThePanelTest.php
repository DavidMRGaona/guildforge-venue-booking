<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Resources;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Mockery\MockInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Domain\Exceptions\BookingNotEndedException;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\EditBooking;
use Modules\VenueBookings\Tests\Support\MakesBookings;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;
use Throwable;

/**
 * Completed is final and counts as something that happened (the activity contract reads it), so
 * staff can only complete a booking that has ended, and what they change on a completed booking
 * from the panel is published for the activity contract.
 */
final class CompletingBookingsFromThePanelTest extends ModuleTestCase
{
    use InteractsWithModulePanel;
    use MakesBookings;

    private const string TYPE = 'venue-bookings.booking';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookingResource::class]);
        $this->setUpRoom();
        $this->admin = UserModel::factory()->admin()->create();
        $this->actingAs($this->admin);
        // Sunday 22 November 2026, 10:30 at the venue
        $this->travelTo(CarbonImmutable::create(2026, 11, 22, 10, 30, 0, self::VENUE_TIMEZONE));
    }

    public function test_the_edit_page_offers_completed_only_once_the_booking_has_ended(): void
    {
        $ended = $this->makeBooking('confirmed', date: '2026-11-22', start: '08:00', end: '10:00');
        $underWay = $this->makeBooking('confirmed', date: '2026-11-22', start: '09:00', end: '11:00');
        $future = $this->makeBooking('confirmed', date: '2026-11-23', start: '08:00', end: '10:00');

        Livewire::test(EditBooking::class, ['record' => $ended->id])
            ->assertFormFieldExists('status', fn ($field): bool => array_key_exists('completed', $field->getOptions()));

        foreach ([$underWay, $future] as $booking) {
            Livewire::test(EditBooking::class, ['record' => $booking->id])
                ->assertFormFieldExists('status', fn ($field): bool => ! array_key_exists('completed', $field->getOptions()));
        }
    }

    public function test_a_booking_that_has_not_ended_is_not_completed_even_if_the_request_asks_for_it(): void
    {
        $underWay = $this->makeBooking('confirmed', date: '2026-11-22', start: '09:00', end: '11:00');

        try {
            Livewire::test(EditBooking::class, ['record' => $underWay->id])
                ->fillForm(['status' => 'completed'])
                ->call('save');
        } catch (Throwable) {
            // Refusing with an error is fine; only the booking matters
        }

        $this->assertSame('confirmed', $underWay->fresh()?->getRawOriginal('status'));
    }

    public function test_a_booking_that_ended_is_completed_from_the_edit_page(): void
    {
        $ended = $this->makeBooking('confirmed', date: '2026-11-22', start: '08:00', end: '10:00');
        $this->listen();

        Livewire::test(EditBooking::class, ['record' => $ended->id])
            ->fillForm(['status' => 'completed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('completed', $ended->fresh()?->getRawOriginal('status'));
        $this->assertPublished($ended->id);
    }

    public function test_the_status_is_checked_again_when_saving_against_the_clock_of_that_moment(): void
    {
        $booking = $this->makeBooking('confirmed', date: '2026-11-22', start: '08:00', end: '10:00');
        $page = Livewire::test(EditBooking::class, ['record' => $booking->id])->fillForm(['status' => 'completed']);

        // The page was loaded after the end; saving happens with the clock before it (a moved booking, say)
        $this->travelTo(CarbonImmutable::create(2026, 11, 22, 9, 0, 0, self::VENUE_TIMEZONE));
        $page->call('save')->assertHasFormErrors(['status']);

        $this->assertSame('confirmed', $booking->fresh()?->getRawOriginal('status'));
    }

    public function test_the_page_says_so_when_the_domain_refuses_a_completion_the_form_let_through(): void
    {
        $booking = $this->makeBooking('confirmed', date: '2026-11-22', start: '08:00', end: '10:00');
        $this->mock(BookingServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('markCompleted')->once()->andThrow(BookingNotEndedException::forSlot('2026-11-22', '10:00'));
        });

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->fillForm(['status' => 'completed'])
            ->call('save')
            ->assertNotified(__('venue-bookings::messages.notifications.booking_not_ended'));

        $this->assertSame('confirmed', $booking->fresh()?->getRawOriginal('status'));
    }

    public function test_changing_who_booked_a_completed_booking_from_the_panel_is_published(): void
    {
        $booking = $this->makeBooking('completed', date: '2026-11-10');
        $other = UserModel::factory()->create();
        $this->listen();

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->fillForm(['user_id' => $other->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($other->id, $booking->fresh()?->user_id);
        $this->assertPublished($booking->id);
    }

    public function test_saving_a_completed_booking_without_touching_its_facts_publishes_nothing(): void
    {
        $booking = $this->makeBooking('completed', date: '2026-11-10');
        $this->listen();

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->fillForm(['admin_notes' => 'Dejó la sala recogida'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Dejó la sala recogida', $booking->fresh()?->admin_notes);
        $this->assertNothingPublished();
    }

    public function test_deleting_a_completed_booking_from_the_panel_is_published(): void
    {
        $booking = $this->makeBooking('completed', date: '2026-11-10');
        $this->listen();

        Livewire::test(EditBooking::class, ['record' => $booking->id])
            ->callAction(DeleteAction::class);

        $this->assertNull($booking->fresh());
        $this->assertPublished($booking->id);
    }

    private function listen(): void
    {
        Event::fake([ActivityRecorded::class]);
        // The publisher is scoped and keeps the dispatcher it was built with
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }

    private function assertPublished(string $bookingId): void
    {
        Event::assertDispatched(ActivityRecorded::class, static fn (ActivityRecorded $recorded): bool => $recorded->sourceType === self::TYPE && $recorded->subjectId === $bookingId);
    }

    private function assertNothingPublished(): void
    {
        Event::assertNotDispatched(ActivityRecorded::class, static fn (ActivityRecorded $recorded): bool => $recorded->sourceType === self::TYPE);
    }
}
