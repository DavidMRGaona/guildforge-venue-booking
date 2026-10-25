<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;
use Modules\VenueBookings\Domain\Exceptions\BookingCancellationNotAllowedException;
use Modules\VenueBookings\Domain\Exceptions\BookingLimitExceededException;
use Modules\VenueBookings\Domain\Exceptions\BookingNotCancellableException;
use Modules\VenueBookings\Domain\Exceptions\BookingNotFoundException;
use Modules\VenueBookings\Domain\Exceptions\MaximumSlotsExceededException;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotOutsideScheduleException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\TooFarToBookException;
use Modules\VenueBookings\Domain\Exceptions\TooSoonToBookException;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldConfig;
use Modules\VenueBookings\Http\Requests\StoreBookingRequest;

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingServiceInterface $bookingService,
        private readonly BookingQueryServiceInterface $queryService,
        private readonly BookingFieldConfigServiceInterface $fieldConfigService,
        private readonly SeoMetadataFactoryInterface $seo,
    ) {}

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $validated = $request->validated();

        $rawFieldValues = $validated['field_values'] ?? [];

        $fieldConfigs = $this->fieldConfigService->getVisibleFields($validated['resource_id'], $user);
        $fieldConfigsByKey = [];
        foreach ($fieldConfigs as $config) {
            $fieldConfigsByKey[$config->key] = $config;
        }

        // A game table, campaign or tournament is only read from its field, when the user is offered
        // it (and the request validated the id against the offer); it goes to its own column
        $associationId = static fn (string $key): mixed => isset($fieldConfigsByKey[$key])
            ? ($rawFieldValues[$key] ?? null)
            : null;
        $gameTableId = $associationId(BookingFieldConfig::GAME_TABLE_KEY);
        $campaignId = $associationId(BookingFieldConfig::CAMPAIGN_KEY);
        $tournamentId = $associationId(BookingFieldConfig::TOURNAMENT_KEY);
        unset(
            $rawFieldValues[BookingFieldConfig::GAME_TABLE_KEY],
            $rawFieldValues[BookingFieldConfig::CAMPAIGN_KEY],
            $rawFieldValues[BookingFieldConfig::TOURNAMENT_KEY],
        );

        $fieldValues = [];
        foreach ($rawFieldValues as $key => $value) {
            if (isset($fieldConfigsByKey[$key])) {
                $config = $fieldConfigsByKey[$key];
                $fieldValues[] = [
                    'field_key' => $key,
                    'field_label' => $config->label,
                    'value' => $value,
                    'visibility' => $config->visibility,
                ];
            }
        }

        $dto = new CreateBookingDTO(
            resourceId: $validated['resource_id'],
            userId: (string) $user->id,
            date: $validated['date'],
            startTime: $validated['start_time'],
            endTime: $validated['end_time'],
            fieldValues: $fieldValues,
            gameTableId: $gameTableId,
            tournamentId: $tournamentId,
            campaignId: $campaignId,
        );

        try {
            $this->bookingService->createBooking($dto);

            return redirect()
                ->route('bookings.my-bookings')
                ->with('success', __('venue-bookings::messages.frontend.booking_created'));
        } catch (BookableResourceUnavailableException) {
            return back()->with('error', __('venue-bookings::messages.frontend.resource_unavailable'));
        } catch (BookingAlreadyStartedException) {
            // Shown inside the booking form, which stays open on errors (flash messages close it)
            return back()->withErrors([
                'start_time' => __('venue-bookings::messages.frontend.booking_already_started'),
            ]);
        } catch (SlotOutsideScheduleException) {
            // Shown inside the booking form, like the start cutoff
            return back()->withErrors([
                'start_time' => __('venue-bookings::messages.frontend.slot_outside_schedule'),
            ]);
        } catch (SlotUnavailableException) {
            return back()->with('error', __('venue-bookings::messages.frontend.slot_unavailable'));
        } catch (BookingLimitExceededException) {
            return back()->with('error', __('venue-bookings::messages.frontend.limit_exceeded'));
        } catch (TooSoonToBookException) {
            return back()->with('error', __('venue-bookings::messages.frontend.too_soon'));
        } catch (TooFarToBookException) {
            return back()->with('error', __('venue-bookings::messages.frontend.too_far'));
        } catch (MinimumSlotsNotMetException) {
            return back()->with('error', __('venue-bookings::messages.frontend.minimum_slots_not_met'));
        } catch (MaximumSlotsExceededException) {
            return back()->with('error', __('venue-bookings::messages.frontend.maximum_slots_exceeded'));
        } catch (BookingBusyException) {
            return back()->with('error', __('venue-bookings::messages.frontend.booking_busy'));
        }
    }

    public function myBookings(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $bookings = $this->queryService->getUserBookings((string) $user->id);

        return Inertia::render('VenueBookings/MyBookings', [
            'bookings' => array_map(fn ($b) => $b->toArray(), $bookings),
            // Every booking listed is the user's own, so one flag covers them all
            'canCancel' => $this->bookingService->canCancel((string) $user->id, (string) $user->id),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('venue-bookings::messages.seo.my_bookings_title'),
                noindex: true,
            ))->toArray(),
        ]);
    }

    public function cancel(Request $request, string $booking): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        // A malformed or missing booking answers like someone else's, so the
        // endpoint does not tell which booking ids exist.
        $forbidden = __('venue-bookings::messages.frontend.booking_cancel_forbidden');

        if (! Str::isUuid($booking)) {
            abort(403, $forbidden);
        }

        /** @var array{reason?: string|null} $validated */
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $dto = new CancelBookingDTO(
            bookingId: $booking,
            userId: (string) $user->id,
            reason: $validated['reason'] ?? null,
        );

        try {
            $this->bookingService->cancelBooking($dto);

            return back()->with('success', __('venue-bookings::messages.frontend.booking_cancelled'));
        } catch (BookingCancellationNotAllowedException $e) {
            // Only someone allowed to cancel this booking learns that it has started
            if ($e->bookingHasStarted()) {
                return back()->with('error', __('venue-bookings::messages.frontend.booking_already_started_cancel'));
            }

            abort(403, $forbidden);
        } catch (BookingNotFoundException) {
            abort(403, $forbidden);
        } catch (BookingNotCancellableException) {
            return back()->with('error', __('venue-bookings::messages.frontend.booking_not_cancellable'));
        }
    }
}
