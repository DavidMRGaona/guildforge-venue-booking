<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use ArrayObject;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\EventFake;
use Illuminate\Testing\PendingCommand;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Console\Commands\CompletePastBookingsCommand;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingId;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Tests\Support\MakesBookings;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Everything that changes the facts of a booking is published again, so whoever keeps a record
 * corrects it: a booking completed (by staff, by the hourly task or when it is created that way),
 * one that stops being completed, whose person, day or hours change, or that goes (alone, with its
 * space or with its account).
 *
 * Nothing becomes completed by time alone: the hourly `complete-past` task writes the status, so
 * the model's own save is what announces it and no separate announcer is needed.
 */
final class ActivityChangePublishingTest extends ModuleTestCase
{
    use MakesBookings;

    private const string TYPE = 'venue-bookings.booking';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private EventFake $events;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();
        $this->setUpRoom();
    }

    public function test_a_booking_completed_by_staff_is_published_once(): void
    {
        $booking = $this->makeBooking('confirmed');
        $this->listen();

        $booking->update(['status' => 'completed']);

        $this->assertPublishedOnly([$booking->id]);
    }

    public function test_a_booking_created_already_completed_is_published(): void
    {
        $this->listen();

        $booking = $this->makeBooking('completed');

        $this->assertPublishedOnly([$booking->id]);
    }

    public function test_completing_through_the_service_is_published(): void
    {
        $booking = $this->makeBooking('confirmed', date: '2026-11-01');
        $this->listen();

        $this->app->make(BookingServiceInterface::class)->markCompleted($booking->id);

        $this->assertPublishedOnly([$booking->id]);
    }

    public function test_the_hourly_task_that_completes_past_bookings_publishes_them(): void
    {
        $this->app->make(Kernel::class)->registerCommand($this->app->make(CompletePastBookingsCommand::class));
        config()->set('modules.settings.venue-bookings.auto_complete_enabled', true);
        config()->set('modules.settings.venue-bookings.auto_complete_after_hours', 24);
        $this->travelTo(CarbonImmutable::create(2026, 11, 12, 10, 0, 0, self::VENUE_TIMEZONE));
        $due = $this->makeBooking('confirmed', date: '2026-11-10');
        $notYet = $this->makeBooking('confirmed', date: '2026-11-12', start: '08:00', end: '09:00');
        $this->listen();

        $command = $this->artisan('venue-bookings:complete-past');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();

        $this->assertSame('completed', $due->fresh()?->status->value);
        $this->assertPublishedOnly([$due->id]);
        $this->assertSame('confirmed', $notYet->fresh()?->status->value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statusesACompletedBookingCanBeChangedTo(): array
    {
        return [
            'cancelled' => ['cancelled'],
            'no show' => ['no_show'],
            'confirmed' => ['confirmed'],
            'pending' => ['pending'],
        ];
    }

    #[DataProvider('statusesACompletedBookingCanBeChangedTo')]
    public function test_a_completed_booking_that_stops_being_completed_is_published(string $status): void
    {
        $booking = $this->makeBooking('completed');
        $this->listen();

        $booking->update(['status' => $status]);

        $this->assertPublishedOnly([$booking->id]);
    }

    public function test_a_booking_that_changes_between_statuses_that_never_count_publishes_nothing(): void
    {
        $booking = $this->makeBooking('pending');
        $this->listen();

        $booking->update(['status' => 'confirmed']);
        $booking->update(['status' => 'cancelled']);
        $booking->update(['status' => 'rejected']);
        $this->makeBooking('expired');
        $booking->delete();

        $this->assertNothingPublished();
    }

    public function test_changing_who_booked_publishes_the_booking_once(): void
    {
        $booking = $this->makeBooking('completed');
        $this->listen();

        $booking->update(['user_id' => UserModel::factory()->create()->id]);

        $this->assertPublishedOnly([$booking->id]);
    }

    public function test_moving_a_completed_booking_to_another_day_or_hours_is_published(): void
    {
        $booking = $this->makeBooking('completed');
        $this->listen();

        $booking->update(['date' => '2026-11-11']);
        $this->assertPublishedOnly([$booking->id]);

        $this->listen();
        $booking->update(['start_time' => '16:00', 'end_time' => '17:00']);
        $this->assertPublishedOnly([$booking->id]);
    }

    public function test_a_save_that_changes_nothing_the_facts_use_publishes_nothing(): void
    {
        $booking = $this->makeBooking('completed');
        $other = $this->makeRoom('Sala pequeña');
        $this->listen();

        $booking->update(['admin_notes' => 'Trae su propio material', 'cancellation_reason' => null]);
        $booking->update(['resource_id' => $other->id]);
        $booking->update(['reminder_sent_at' => now()]);

        $this->assertNothingPublished();
    }

    public function test_a_completed_booking_deleted_is_published(): void
    {
        $booking = $this->makeBooking('completed');
        $this->listen();

        $booking->delete();

        $this->assertPublishedOnly([$booking->id]);
    }

    public function test_deleting_through_the_repository_is_published_too(): void
    {
        $booking = $this->makeBooking('completed');
        $ignored = $this->makeBooking('cancelled');
        $this->listen();

        $this->app->make(BookingRepositoryInterface::class)->delete(BookingId::fromString($booking->id));
        $this->app->make(BookingRepositoryInterface::class)->delete(BookingId::fromString($ignored->id));

        $this->assertPublishedOnly([$booking->id]);
        $this->assertSame(0, BookingModel::query()->count());
    }

    public function test_deleting_a_space_publishes_its_completed_bookings_once_they_are_gone(): void
    {
        $room = $this->makeRoom('Sala que se borra');
        $completed = $this->makeBooking('completed', room: $room);
        $alsoCompleted = $this->makeBooking('completed', date: '2026-11-11', room: $room);
        $cancelled = $this->makeBooking('cancelled', room: $room);
        $elsewhere = $this->makeBooking('completed');
        $remaining = $this->recordWhatIsLeftWhenPublished();

        $room->delete();

        // The database cascades the bookings away, so nothing but the model's own events could tell
        $this->assertSame([$completed->id => 0, $alsoCompleted->id => 0], $remaining->getArrayCopy());
        $this->assertNotNull(BookingModel::query()->find($elsewhere->id));
        $this->assertNull(BookingModel::query()->find($cancelled->id));
    }

    public function test_deleting_a_space_through_the_repository_is_published_too(): void
    {
        $room = $this->makeRoom('Sala del repositorio');
        $booking = $this->makeBooking('completed', room: $room);
        $this->listen();

        $this->app->make(BookableResourceRepositoryInterface::class)->delete(BookableResourceId::fromString($room->id));

        $this->assertPublishedOnly([$booking->id]);
        $this->assertSame(0, BookingModel::query()->where('resource_id', $room->id)->count());
    }

    public function test_removing_an_account_for_good_publishes_its_completed_bookings_once_it_is_gone(): void
    {
        $leaver = UserModel::factory()->create();
        $completed = $this->makeBooking('completed', $leaver);
        $cancelled = $this->makeBooking('cancelled', $leaver);
        $someoneElses = $this->makeBooking('completed');
        $remaining = $this->recordWhatIsLeftWhenPublished();

        $leaver->forceDelete();

        // A forced deletion opens no transaction: the bookings must be gone when the consumer is told
        $this->assertSame([$completed->id => 0], $remaining->getArrayCopy());
        $this->assertNotNull(BookingModel::query()->find($someoneElses->id));
        $this->assertNull(BookingModel::query()->find($cancelled->id));
    }

    public function test_deactivating_an_account_publishes_nothing_because_its_facts_stay(): void
    {
        $user = UserModel::factory()->create();
        $this->makeBooking('completed', $user);
        $this->listen();

        $user->delete();

        $this->assertNothingPublished();
    }

    public function test_a_change_that_is_rolled_back_publishes_nothing(): void
    {
        $booking = $this->makeBooking('confirmed');
        $this->listen();

        try {
            DB::transaction(function () use ($booking): void {
                $booking->update(['status' => 'completed']);

                throw new RuntimeException('Undone');
            });
        } catch (RuntimeException) {
            // The change was undone on purpose
        }

        $this->assertNothingPublished();
    }

    /**
     * @param  list<string>  $ids
     */
    private function assertPublishedOnly(array $ids): void
    {
        $dispatched = $this->events->dispatched(ActivityRecorded::class)->map(static fn (array $arguments): ActivityRecorded => $arguments[0])
            ->filter(static fn (ActivityRecorded $recorded): bool => $recorded->sourceType === self::TYPE)
            ->map(static fn (ActivityRecorded $recorded): string => $recorded->subjectId)
            ->values()
            ->all();

        $this->assertEqualsCanonicalizing($ids, $dispatched);
        $this->assertSame($dispatched, array_values(array_unique($dispatched)), 'A booking is announced once per change.');
    }

    private function assertNothingPublished(): void
    {
        // The core publishes its own account sources when an account goes: only this module's type matters
        $this->assertSame([], $this->events->dispatched(ActivityRecorded::class)
            ->filter(static fn (array $arguments): bool => $arguments[0]->sourceType === self::TYPE)
            ->all());
    }

    /**
     * Listens from here on, so what the setup did is not counted.
     */
    private function listen(): void
    {
        $this->events = Event::fake([ActivityRecorded::class]);
        // The publisher is scoped and keeps the dispatcher it was built with
        $this->app->forgetInstance(ActivityPublisherInterface::class);
    }

    /**
     * Swaps the publisher for one that counts how many bookings of the subject are still stored at
     * the moment it is called.
     *
     * @return ArrayObject<string, int>
     */
    private function recordWhatIsLeftWhenPublished(): ArrayObject
    {
        /** @var ArrayObject<string, int> $remaining */
        $remaining = new ArrayObject;

        $this->app->instance(ActivityPublisherInterface::class, new readonly class($remaining) implements ActivityPublisherInterface
        {
            /** @param  ArrayObject<string, int>  $remaining */
            public function __construct(private ArrayObject $remaining) {}

            public function recorded(string $sourceType, string $subjectId): void
            {
                if ($sourceType === 'venue-bookings.booking') {
                    $this->remaining[$subjectId] = BookingModel::query()->whereKey($subjectId)->count();
                }
            }
        });

        return $remaining;
    }
}
