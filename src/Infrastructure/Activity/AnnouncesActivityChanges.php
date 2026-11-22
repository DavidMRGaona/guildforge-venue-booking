<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeInterface;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

/**
 * Publishes, through the core's ActivityPublisherInterface, every change to the facts of a
 * booking: one that becomes completed (staff, the panel, the hourly `complete-past` task or a
 * booking stored that way) or stops being completed, whose person, day or hours change while it
 * is completed, or that goes (alone, with its space or with its account).
 *
 * Nothing becomes completed by time alone: the hourly task writes the status, and that write is
 * what is announced here, so unlike the events of another module this needs no scheduled
 * announcer. Bookings are saved by the panel, the repository, the service and plain models, so
 * the provider wires this to the models rather than to any one path.
 *
 * A booking that does not count is not published: pending, confirmed, cancelled and the rest
 * have no facts, and a change between them has nothing to say. Publishing for a save that
 * changed nothing would be harmless, but it is noise a consumer would pay for.
 */
final readonly class AnnouncesActivityChanges
{
    public function __construct(
        private CompletedBookings $completed,
        private ActivityPublisherInterface $activity,
    ) {}

    /**
     * Inside `saved` the model still holds the values it had before the save (a new one has none),
     * and `getOriginal()` what it was before this save.
     */
    public function bookingSaved(BookingModel $booking): void
    {
        $was = $this->completed->counts($booking->getRawOriginal('status'));
        $is = $this->completed->counts($booking->status);

        if (! $was && ! $is) {
            return;
        }

        // Still completed, and nothing the facts use moved
        if ($was && $is && ! $this->factsMoved($booking)) {
            return;
        }

        $this->activity->recorded(BookingActivitySource::TYPE, $booking->id);
    }

    public function bookingDeleted(BookingModel $booking): void
    {
        if ($this->completed->counts($booking->status)) {
            $this->activity->recorded(BookingActivitySource::TYPE, $booking->id);
        }
    }

    /**
     * The completed bookings of a space, asked before it is removed (`deleting`): its bookings
     * go with it (a database cascade, no model event) and nothing could be asked after.
     *
     * @return list<string>
     */
    public function completedIdsOfSpace(BookableResourceModel $space): array
    {
        return $this->ids($this->completed->query()->where('resource_id', $space->id)->pluck('id')->all());
    }

    /**
     * The completed bookings of an account, asked before it is removed (`forceDeleting`), for the
     * same reason.
     *
     * @return list<string>
     */
    public function completedIdsOfAccount(UserModel $user): array
    {
        return $this->ids($this->completed->query()->where('user_id', (string) $user->getKey())->pluck('id')->all());
    }

    /**
     * Once the space or the account is gone (`deleted`, `forceDeleted`): a cascade opens no
     * transaction of its own, so publishing earlier would let the consumer read the bookings
     * still there. Deactivating an account keeps its bookings, and its facts with them.
     *
     * @param  list<string>  $bookingIds
     */
    public function removed(array $bookingIds): void
    {
        foreach ($bookingIds as $bookingId) {
            $this->activity->recorded(BookingActivitySource::TYPE, $bookingId);
        }
    }

    /**
     * Whether this save changed the person or the moment the booking ended. The values are compared
     * as the model reads them, not through `isDirty()`: PostgreSQL stores times as `HH:MM:SS` and
     * the model keeps `HH:MM`, so the same time reads as changed there.
     */
    private function factsMoved(BookingModel $booking): bool
    {
        return (string) $booking->getOriginal('user_id') !== (string) $booking->getAttribute('user_id')
            || $this->day($booking->getOriginal('date')) !== $this->day($booking->getAttribute('date'))
            || (string) $booking->getOriginal('start_time') !== (string) $booking->getAttribute('start_time')
            || (string) $booking->getOriginal('end_time') !== (string) $booking->getAttribute('end_time');
    }

    private function day(mixed $date): string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : (string) $date;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<string>
     */
    private function ids(array $ids): array
    {
        return array_values(array_map(static fn (mixed $id): string => (string) $id, $ids));
    }
}
