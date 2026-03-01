<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Domain\Exceptions\BookingLimitExceededException;
use Modules\VenueBookings\Domain\Exceptions\BookingNotCancellableException;
use Modules\VenueBookings\Domain\Exceptions\BookingNotFoundException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\TooFarToBookException;
use Modules\VenueBookings\Domain\Exceptions\TooSoonToBookException;
use Modules\VenueBookings\Http\Requests\StoreBookingRequest;

final class BookingController extends Controller
{
    public function __construct(
        private readonly BookingServiceInterface $bookingService,
        private readonly BookingQueryServiceInterface $queryService,
        private readonly BookingFieldConfigServiceInterface $fieldConfigService,
    ) {}

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $validated = $request->validated();

        $rawFieldValues = $validated['field_values'] ?? [];

        // Extract association IDs from field_values (frontend sends them inside field_values)
        $gameTableId = $validated['game_table_id'] ?? ($rawFieldValues['game_table_id'] ?? null);
        $campaignId = $validated['campaign_id'] ?? ($rawFieldValues['campaign_id'] ?? null);
        $tournamentId = $validated['tournament_id'] ?? ($rawFieldValues['tournament_id'] ?? null);

        // Remove from field values so they're stored in dedicated columns, not in JSON
        unset($rawFieldValues['game_table_id'], $rawFieldValues['campaign_id'], $rawFieldValues['tournament_id']);

        $fieldConfigs = $this->fieldConfigService->getVisibleFields($validated['resource_id'], $user);
        $fieldConfigsByKey = [];
        foreach ($fieldConfigs as $config) {
            $fieldConfigsByKey[$config->key] = $config;
        }

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
            eventId: $validated['event_id'] ?? null,
            gameTableId: $gameTableId,
            tournamentId: $tournamentId,
            campaignId: $campaignId,
        );

        try {
            $this->bookingService->createBooking($dto);

            return redirect()
                ->route('bookings.my-bookings')
                ->with('success', __('venue-bookings::messages.frontend.booking_created'));
        } catch (SlotUnavailableException) {
            return back()->with('error', __('venue-bookings::messages.frontend.slot_unavailable'));
        } catch (BookingLimitExceededException) {
            return back()->with('error', __('venue-bookings::messages.frontend.limit_exceeded'));
        } catch (TooSoonToBookException) {
            return back()->with('error', __('venue-bookings::messages.frontend.too_soon'));
        } catch (TooFarToBookException) {
            return back()->with('error', __('venue-bookings::messages.frontend.too_far'));
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
        ]);
    }

    public function cancel(Request $request, string $booking): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $dto = new CancelBookingDTO(
            bookingId: $booking,
            userId: (string) $user->id,
            reason: $request->input('reason'),
        );

        try {
            $this->bookingService->cancelBooking($dto);

            return back()->with('success', __('venue-bookings::messages.frontend.booking_cancelled'));
        } catch (BookingNotFoundException) {
            return back()->with('error', __('venue-bookings::messages.frontend.booking_not_found'));
        } catch (BookingNotCancellableException) {
            return back()->with('error', __('venue-bookings::messages.frontend.booking_not_cancellable'));
        }
    }
}
