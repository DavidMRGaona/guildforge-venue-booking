<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Proposals;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Domain\Entities\Event;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Domain\ValueObjects\EventId;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\Services\BookingMailSubjects;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;
use Modules\VenueBookings\Domain\Exceptions\MaximumSlotsExceededException;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotOutsideScheduleException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\BookingRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;

/**
 * «¿Necesitas reservar un espacio?» in event proposals (core#581). Approving the proposal books
 * the space for the event's day and hours, read in the venue timezone, through BookingService:
 * overlaps, the space's slots and its status apply; the settings rules and the approval of the
 * space do not, since approving the event is already the staff's review. The booking is
 * confirmed, in the name of whoever proposed the event and linked to it.
 */
final readonly class BookingProposalRequest implements EventProposalRequestInterface
{
    public const string KEY = 'venue-bookings.booking';

    private const string RESOURCE = 'resource';

    private const string NOTE = 'note';

    private const string MESSAGES = 'venue-bookings::messages.proposal_request.';

    public function __construct(
        private BookingServiceInterface $bookings,
        private BookingRepositoryInterface $bookingRepository,
        private BookableResourceRepositoryInterface $resources,
        private EventRepositoryInterface $events,
        private BookingSettingsReader $settings,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __(self::MESSAGES.'label');
    }

    public function question(): string
    {
        return __(self::MESSAGES.'question');
    }

    public function order(): int
    {
        return 40;
    }

    public function fields(): array
    {
        $options = $this->activeResourceOptions();

        // A select needs options: with no space taking bookings the request is not offered
        if ($options === []) {
            return [];
        }

        return [
            ProposalRequestFieldDTO::select(self::RESOURCE, __(self::MESSAGES.'fields.resource'), $options, required: true),
            ProposalRequestFieldDTO::text(self::NOTE, __(self::MESSAGES.'fields.note'), helperText: __(self::MESSAGES.'fields.note_help')),
        ];
    }

    public function isAvailable(): bool
    {
        return $this->activeResourceOptions() !== [];
    }

    public function apply(EventId $eventId, array $values): void
    {
        $resource = $this->resourceOf($values);

        if (! $resource instanceof BookableResource) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.no_resource'));
        }

        $event = $this->events->findById($eventId);

        if (! $event instanceof Event) {
            throw new LogicException("The event {$eventId->value()} being approved does not exist.");
        }

        $authorId = $event->proposal()?->proposedBy;

        if ($authorId === null) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.no_author'));
        }

        // The event's day and hours as the venue reads them
        $timezone = $this->settings->getDateTimeZone();
        $start = $event->startDate()->setTimezone($timezone);
        $end = $event->endDate()->setTimezone($timezone);

        if (! $this->isOneDay($start, $end)) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.multi_day'));
        }

        if ($this->bookingRepository->hasActiveBookingForEvent($eventId->value(), $resource->id)) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.already_booked', ['resource' => $resource->name]));
        }

        $date = $start->format('Y-m-d');
        $startTime = $start->format('H:i');
        $endTime = $end->format('H:i');
        $replacements = [
            'resource' => $resource->name,
            'date' => BookingMailSubjects::displayDate($date),
            'start' => $startTime,
            'end' => $endTime,
        ];

        $note = $values[self::NOTE] ?? null;

        try {
            $this->bookings->createBookingForApprovedEvent(new CreateBookingDTO(
                resourceId: $resource->id->value,
                userId: $authorId,
                date: $date,
                startTime: $startTime,
                endTime: $endTime,
                eventId: $eventId->value(),
            ), adminNotes: is_string($note) ? $note : null);
        } catch (SlotUnavailableException) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.slot_taken', $replacements));
        } catch (SlotOutsideScheduleException|MinimumSlotsNotMetException|MaximumSlotsExceededException) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.outside_schedule', $replacements));
        } catch (BookableResourceUnavailableException) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.resource_unavailable', $replacements));
        } catch (BookingAlreadyStartedException) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.already_started'));
        } catch (BookingBusyException) {
            throw ProposalRequestRefusedException::because(__(self::MESSAGES.'refusals.busy', $replacements));
        }
    }

    public function describe(array $values): string
    {
        $resource = $this->resourceOf($values);

        if (! $resource instanceof BookableResource) {
            return __(self::MESSAGES.'describe.unknown_resource');
        }

        $note = $values[self::NOTE] ?? null;

        return is_string($note) && $note !== ''
            ? __(self::MESSAGES.'describe.with_note', ['resource' => $resource->name, 'note' => $note])
            : __(self::MESSAGES.'describe.resource', ['resource' => $resource->name]);
    }

    /**
     * The spaces that take bookings, by id, in their order.
     *
     * @return array<string, string>
     */
    private function activeResourceOptions(): array
    {
        $options = [];

        foreach ($this->resources->getActive() as $resource) {
            $options[$resource->id->value] = $resource->name;
        }

        return $options;
    }

    /**
     * The space the values name, active or not; null when there is none.
     *
     * @param  array<string, bool|int|string|null>  $values
     */
    private function resourceOf(array $values): ?BookableResource
    {
        $id = $values[self::RESOURCE] ?? null;

        if (! is_string($id) || $id === '') {
            return null;
        }

        try {
            return $this->resources->find(BookableResourceId::fromString($id));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Whether the event starts and ends on the same day, with some time in between: a booking
     * holds one day's range. Longer events are booked by hand.
     */
    private function isOneDay(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return $start->format('Y-m-d') === $end->format('Y-m-d')
            && $start->format('H:i') < $end->format('H:i');
    }
}
