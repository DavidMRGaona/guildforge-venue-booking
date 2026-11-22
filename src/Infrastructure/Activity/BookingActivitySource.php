<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\VenueBookings\Application\Services\BookingMailSubjects;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Throwable;

/**
 * «Reservas del local» for the activity contract (core#774): every completed booking, with the
 * person who booked as a `booker` fact at the moment the booking ended.
 *
 * Who booked is NOT shown to other members on the site (the calendar says «Reservado», the detail
 * names the person only to whoever manages bookings, `/perfil` lists a person's own bookings), so
 * this is an accepted exception to «only what any signed-in member can know», the same as who
 * played or attended: the achievements module only builds totals from these facts («8 reservas»)
 * and never names a booking to somebody `describe()` would not show it to.
 *
 * The answer is the same for everybody and never reads the signed-in user, the session or the
 * request, since a queued job runs it. Bookings always belong to an account; anonymized accounts
 * are dropped by the core's reader.
 */
final readonly class BookingActivitySource implements ActivitySourceInterface
{
    public const string TYPE = 'venue-bookings.booking';

    private const string CURSOR_PREFIX = 'vb1|';

    /** The core never asks for more; a larger limit is not trusted either, so a call has a ceiling */
    private const int MAX_PAGE = ActivityHistoryPageDTO::MAX_LIMIT;

    /** Everything the facts need, and nothing of what staff write on a booking */
    private const array FACT_COLUMNS = ['id', 'user_id', 'date', 'start_time', 'end_time'];

    public function __construct(
        private CompletedBookings $completed,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::VenueBooking;
    }

    public function label(): string
    {
        return __('venue-bookings::messages.activity.booking_label');
    }

    public function facts(string $subjectId): array
    {
        $id = strtolower($subjectId);

        if (! Str::isUuid($id)) {
            return [];
        }

        $booking = $this->completed->query()->whereKey($id)->first(self::FACT_COLUMNS);

        if (! $booking instanceof BookingModel) {
            return [];
        }

        return $this->factsByBooking(new Collection([$booking]))[$booking->id] ?? [];
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The limit of a history page must be at least 1.');
        }

        $limit = min($limit, self::MAX_PAGE);
        $after = $cursor === null ? null : $this->decode($cursor);

        $bookings = $this->completed->query()
            ->when($after !== null, static fn ($query) => $query->where('id', '>', $after))
            ->orderBy('id')
            ->limit($limit + 1)
            ->get(self::FACT_COLUMNS);

        $page = $bookings->take($limit);
        $last = $page->last();
        $facts = $this->factsByBooking($page);

        return new ActivityHistoryPageDTO(
            subjects: array_values($page->map(static fn (BookingModel $booking): ActivitySubjectFactsDTO => new ActivitySubjectFactsDTO($booking->id, $facts[$booking->id] ?? []))->all()),
            nextCursor: $bookings->count() > $limit && $last instanceof BookingModel ? $this->encode($last->id) : null,
        );
    }

    /**
     * Only bookings that count (completed), as the other sources do, and with the module's own
     * rule, `BookingPolicy::view()`: the person who booked sees their own
     * booking, and whoever may list every booking in the panel sees any. A guest and any other
     * member see none, and no title ever names the person who booked.
     */
    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        if (! $viewer instanceof UserModel) {
            return [];
        }

        $ids = array_values(array_filter(
            array_map(strtolower(...), $subjectIds),
            static fn (string $id): bool => Str::isUuid($id),
        ));

        if ($ids === []) {
            return [];
        }

        $gate = Gate::forUser($viewer);
        $seesEvery = $gate->allows('viewAny', BookingModel::class);
        $described = [];

        foreach ($this->completed->query()->with('resource:id,name')->whereIn('id', $ids)->get(['id', 'user_id', 'resource_id', 'date']) as $booking) {
            if (! $gate->allows('view', $booking)) {
                continue;
            }

            $described[$booking->id] = new ActivitySubjectDTO(
                $booking->id,
                __('venue-bookings::messages.activity.booking_title', [
                    'resource' => $booking->resource->name,
                    'date' => BookingMailSubjects::displayDate($booking->date->format('Y-m-d')),
                ]),
                $seesEvery ? $this->panelPath($booking) : route('bookings.my-bookings', [], false),
            );
        }

        return $described;
    }

    /**
     * One fact per booking, in one pass over the bookings given.
     *
     * @param  Collection<int, BookingModel>  $bookings
     * @return array<string, list<ActivityFactDTO>>
     */
    private function factsByBooking(Collection $bookings): array
    {
        $facts = [];

        foreach ($bookings as $booking) {
            $facts[$booking->id] = [new ActivityFactDTO(
                userId: $booking->user_id,
                role: ActivityRole::Booker,
                occurredAt: $this->completed->endedAt($booking),
            )];
        }

        return $facts;
    }

    /**
     * The booking's page in the panel. A link that cannot be built is left out: the title stays.
     */
    private function panelPath(BookingModel $booking): ?string
    {
        try {
            return BookingResource::getUrl('edit', ['record' => $booking->id], isAbsolute: false);
        } catch (Throwable) {
            return null;
        }
    }

    private function encode(string $bookingId): string
    {
        return rtrim(strtr(base64_encode(self::CURSOR_PREFIX.$bookingId), '+/', '-_'), '=');
    }

    private function decode(string $cursor): string
    {
        $decoded = preg_match('/^[A-Za-z0-9_-]+$/D', $cursor) === 1
            ? base64_decode(strtr($cursor, '-_', '+/'), true)
            : false;

        if (! is_string($decoded) || ! str_starts_with($decoded, self::CURSOR_PREFIX)) {
            throw new InvalidArgumentException('The history cursor cannot be read.');
        }

        $id = strtolower(substr($decoded, strlen(self::CURSOR_PREFIX)));

        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException('The history cursor cannot be read.');
        }

        return $id;
    }
}
