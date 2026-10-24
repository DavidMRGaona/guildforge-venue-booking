<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookingResource\Pages\Concerns;

use DomainException;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;
use Modules\VenueBookings\Domain\Exceptions\MaximumSlotsExceededException;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotOutsideScheduleException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;

/**
 * Why BookingService refused to create or move a booking from the panel, shown on the field
 * it is about when there is one.
 */
trait ShowsBookingRefusals
{
    private function refuse(DomainException|InvalidArgumentException $exception): never
    {
        [$field, $key] = match (true) {
            $exception instanceof BookableResourceUnavailableException => ['resource_id', 'resource_unavailable'],
            $exception instanceof BookingAlreadyStartedException => ['start_time', 'already_started'],
            $exception instanceof SlotUnavailableException => ['start_time', 'slot_unavailable'],
            $exception instanceof MinimumSlotsNotMetException => ['start_time', 'minimum_slots_not_met'],
            $exception instanceof MaximumSlotsExceededException => ['start_time', 'maximum_slots_exceeded'],
            // A range whose start and end are equal is not made of slots either
            $exception instanceof SlotOutsideScheduleException,
            $exception instanceof InvalidArgumentException => ['start_time', 'slot_outside_schedule'],
            $exception instanceof BookingBusyException => [null, 'booking_busy'],
            default => throw $exception,
        };

        $message = __("venue-bookings::messages.panel_refusals.{$key}");

        if ($field === null) {
            Notification::make()->title($message)->danger()->send();

            throw new Halt;
        }

        throw ValidationException::withMessages(["data.{$field}" => $message]);
    }
}
