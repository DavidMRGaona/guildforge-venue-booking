<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Proposals;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\ProposalRequestNotAppliedException;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\CreateBooking;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Infrastructure\Proposals\BookingProposalRequest;
use Modules\VenueBookings\Notifications\BookingConfirmedNotification;
use Modules\VenueBookings\Notifications\BookingCreatedNotification;
use Modules\VenueBookings\Tests\Support\OpensResourceEveryDay;
use Modules\VenueBookings\Tests\Support\Proposals\FailingProposalRequest;
use Modules\VenueBookings\VenueBookingsServiceProvider;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The space booking an event proposal may ask for (vb#135, core#581): approving the proposal
 * books the space through BookingService, confirmed and in the author's name, for one-day events.
 */
final class BookingProposalRequestTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;
    use OpensResourceEveryDay;

    private const string VENUE_TIMEZONE = 'Europe/Madrid';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private BookableResourceModel $room;

    private UserModel $author;

    private UserModel $reviewer;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('modules.settings.venue-bookings.timezone', self::VENUE_TIMEZONE);
        // Spaces that ask for approval: the booking of an approved proposal is confirmed anyway
        config()->set('modules.settings.venue-bookings.approval_mode', 'require_approval');
        $this->travelTo(CarbonImmutable::create(2026, 11, 20, 9, 0, 0, self::VENUE_TIMEZONE));

        // Enabling the module seeds example spaces: only the test's own take bookings
        BookableResourceModel::query()->update(['status' => 'inactive']);
        $this->room = $this->resource('Sala grande');
        $this->openEveryDay($this->room->id, '08:00', '23:00', maxConsecutiveSlots: 10);

        $this->author = UserModel::factory()->create();
        $this->reviewer = $this->editorWithPermissions(['events.view_any', 'events.update', 'events.review']);
    }

    private function resource(string $name, string $status = 'active'): BookableResourceModel
    {
        return BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => $status,
            'sort_order' => 0,
        ]);
    }

    private function request(): EventProposalRequestInterface
    {
        $request = app(EventProposalRequestRegistryInterface::class)->request(BookingProposalRequest::KEY);
        $this->assertInstanceOf(BookingProposalRequest::class, $request);

        return $request;
    }

    /**
     * An event proposal awaiting review, from $start to $end (venue wall times), that asks for the space.
     *
     * @param  array<string, string|null>  $values
     */
    private function proposal(string $start = '2026-12-05 17:00', string $end = '2026-12-05 21:00', ?array $values = null): EventModel
    {
        $event = EventModel::factory()->proposal(ProposalStatus::PendingReview, $this->author)->create([
            'start_date' => CarbonImmutable::parse($start, self::VENUE_TIMEZONE),
            'end_date' => CarbonImmutable::parse($end, self::VENUE_TIMEZONE),
        ]);

        app(EventProposalRequestServiceInterface::class)->saveRequested($event->id, [
            BookingProposalRequest::KEY => ['wanted' => '1', 'values' => $values ?? ['resource' => $this->room->id, 'note' => 'Traemos tableros']],
        ]);

        return $event;
    }

    private function approve(EventModel $event): void
    {
        app(ProposalServiceInterface::class)->approve($this->reviewer->id, ProposalType::Event, $event->id);
    }

    /**
     * The message the reviewer reads when approving fails because of a request.
     */
    private function refusalOf(EventModel $event): string
    {
        try {
            $this->approve($event);
        } catch (ProposalRequestNotAppliedException $exception) {
            $this->assertSame(ProposalStatus::PendingReview, $event->refresh()->proposalStatus());

            return (string) $exception->detail;
        }

        $this->fail('The approval should have been refused.');
    }

    private function bookOtherwise(string $date, string $start, string $end, ?string $eventId = null): void
    {
        BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $this->room->id,
            'user_id' => UserModel::factory()->create()->id,
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'status' => BookingStatus::Confirmed,
            'event_id' => $eventId,
        ]);
    }

    public function test_the_module_registers_the_request_with_its_fields(): void
    {
        $this->resource('Trastero', status: 'inactive');

        $provider = new VenueBookingsServiceProvider($this->app);
        $this->assertSame([BookingProposalRequest::class], $provider->registerEventProposalRequests());

        $request = $this->request();
        $this->assertSame('venue-bookings.booking', $request->key());
        $this->assertSame('Reserva de espacio', $request->label());
        $this->assertSame('¿Necesitas reservar un espacio?', $request->question());
        $this->assertTrue($request->isAvailable());

        $fields = $request->fields();
        $this->assertSame(['resource', 'note'], array_map(fn (ProposalRequestFieldDTO $field): string => $field->name, $fields));
        // Only spaces that take bookings are offered
        $this->assertSame([$this->room->id => 'Sala grande'], $fields[0]->options);
        $this->assertTrue($fields[0]->required);
        $this->assertFalse($fields[1]->required);
    }

    public function test_without_active_spaces_it_is_not_offered(): void
    {
        $this->room->update(['status' => 'inactive']);

        $this->assertFalse($this->request()->isAvailable());
        $this->assertSame([], $this->request()->fields());
    }

    public function test_describe_names_the_space_and_the_note(): void
    {
        $request = $this->request();

        $this->assertSame('Reserva de Sala grande', $request->describe(['resource' => $this->room->id, 'note' => null]));
        $this->assertSame('Reserva de Sala grande (nota: Traemos tableros)', $request->describe(['resource' => $this->room->id, 'note' => 'Traemos tableros']));
        $this->assertSame('Reserva de un espacio que ya no existe', $request->describe(['resource' => (string) Str::uuid(), 'note' => null]));
    }

    public function test_approving_books_the_space_confirmed_in_the_authors_name_for_the_event(): void
    {
        $event = $this->proposal();

        $this->approve($event);

        $this->assertSame(ProposalStatus::Approved, $event->refresh()->proposalStatus());
        $booking = BookingModel::query()->sole();
        $this->assertSame($this->room->id, $booking->resource_id);
        $this->assertSame($this->author->id, $booking->user_id);
        $this->assertSame($event->id, $booking->event_id);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNotNull($booking->confirmed_at);
        $this->assertSame('2026-12-05', $booking->date->format('Y-m-d'));
        $this->assertSame('17:00', substr($booking->start_time, 0, 5));
        $this->assertSame('21:00', substr($booking->end_time, 0, 5));
        $this->assertSame('Traemos tableros', $booking->admin_notes);
    }

    public function test_the_day_and_hours_are_read_in_the_venue_timezone(): void
    {
        // 18:00 in Madrid is 17:00 in the Canary Islands, where this venue is
        $event = $this->proposal('2026-12-05 18:00', '2026-12-05 20:00');
        config()->set('modules.settings.venue-bookings.timezone', 'Atlantic/Canary');

        $this->approve($event);

        $booking = BookingModel::query()->sole();
        $this->assertSame('2026-12-05', $booking->date->format('Y-m-d'));
        $this->assertSame('17:00', substr($booking->start_time, 0, 5));
        $this->assertSame('19:00', substr($booking->end_time, 0, 5));
    }

    public function test_the_settings_rules_do_not_apply_but_the_schedule_does(): void
    {
        config()->set('modules.settings.venue-bookings.max_future_days', 1);
        config()->set('modules.settings.venue-bookings.max_active_bookings_per_user', 0);

        $this->approve($this->proposal());

        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_hours_outside_the_spaces_slots_are_refused(): void
    {
        $message = $this->refusalOf($this->proposal('2026-12-05 17:00', '2026-12-05 21:30'));

        $this->assertStringContainsString('05/12/2026', $message);
        $this->assertStringContainsString('Sala grande', $message);
        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_a_taken_slot_refuses_naming_the_date(): void
    {
        $this->bookOtherwise('2026-12-05', '18:00', '19:00');

        $message = $this->refusalOf($this->proposal());

        $this->assertSame('Sala grande ya está ocupado el 05/12/2026 entre las 17:00 y las 21:00. Elige otro espacio o desmarca la petición y resérvalo a mano.', $message);
        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_a_multi_day_event_is_refused(): void
    {
        $message = $this->refusalOf($this->proposal('2026-12-05 17:00', '2026-12-06 12:00'));

        $this->assertSame('El evento dura más de un día: desmarca la petición y haz las reservas a mano en el panel.', $message);
        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_an_event_already_booking_the_space_is_refused(): void
    {
        $event = $this->proposal();
        $this->bookOtherwise('2026-12-01', '10:00', '11:00', eventId: $event->id);

        $message = $this->refusalOf($event);

        $this->assertSame('El evento ya tiene una reserva de Sala grande.', $message);
        $this->assertSame(1, BookingModel::query()->count());
    }

    public function test_a_space_that_stopped_taking_bookings_stops_the_approval(): void
    {
        $event = $this->proposal();
        $this->room->update(['status' => 'inactive']);

        $this->expectException(ProposalRequestNotAppliedException::class);

        try {
            $this->approve($event);
        } finally {
            $this->assertSame(0, BookingModel::query()->count());
        }
    }

    public function test_the_booking_is_mailed_once_the_approval_commits(): void
    {
        Notification::fake();

        $this->approve($this->proposal());

        Notification::assertSentTo($this->author, BookingConfirmedNotification::class);
    }

    public function test_another_failing_step_rolls_the_booking_back_and_nothing_is_announced(): void
    {
        Notification::fake();
        app(EventProposalRequestRegistryInterface::class)->register(FailingProposalRequest::class, FailingProposalRequest::MODULE);
        $event = $this->proposal();
        app(EventProposalRequestServiceInterface::class)->saveRequested($event->id, [
            BookingProposalRequest::KEY => ['wanted' => '1', 'values' => ['resource' => $this->room->id, 'note' => null]],
            FailingProposalRequest::KEY => ['wanted' => '1', 'values' => []],
        ]);

        $this->assertSame('Otro paso falla.', $this->refusalOf($event));

        $this->assertSame(0, BookingModel::query()->count());
        Notification::assertNotSentTo($this->author, BookingConfirmedNotification::class);
        Notification::assertNothingSentTo($this->reviewer);
        Notification::assertNotSentTo($this->author, BookingCreatedNotification::class);
    }

    public function test_an_unapproved_proposal_books_nothing_and_is_never_offered_as_an_event(): void
    {
        Notification::fake();
        $proposal = $this->proposal();
        $published = EventModel::factory()->published()->create();
        $this->registerOnAdminPanel([BookingResource::class]);

        // Asking for the booking reserves nothing: no calendar, widget or notice sees it
        $this->assertSame(0, BookingModel::query()->count());
        Notification::assertNothingSent();

        $this->actingAs(UserModel::factory()->admin()->create());
        Livewire::test(CreateBooking::class)
            ->assertFormFieldExists('event_id', static fn (Select $field): bool => array_key_exists($published->id, $field->getOptions())
                && ! array_key_exists($proposal->id, $field->getOptions()));
    }
}
