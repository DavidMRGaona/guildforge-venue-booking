<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookingResource\Pages;

use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Domain\Exceptions\BookableResourceUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\BookingAlreadyStartedException;
use Modules\VenueBookings\Domain\Exceptions\BookingBusyException;
use Modules\VenueBookings\Domain\Exceptions\BookingLimitExceededException;
use Modules\VenueBookings\Domain\Exceptions\MaximumSlotsExceededException;
use Modules\VenueBookings\Domain\Exceptions\MinimumSlotsNotMetException;
use Modules\VenueBookings\Domain\Exceptions\SlotOutsideScheduleException;
use Modules\VenueBookings\Domain\Exceptions\SlotUnavailableException;
use Modules\VenueBookings\Domain\Exceptions\TooFarToBookException;
use Modules\VenueBookings\Domain\Exceptions\TooSoonToBookException;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

final class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    // BookingService saves under its lock; a transaction around it would commit after the lock
    // is released, and another request could miss the booking in its overlap check
    protected ?bool $hasDatabaseTransactions = false;

    public function getTitle(): string|Htmlable
    {
        return __('venue-bookings::messages.pages.create_booking');
    }

    /**
     * Convert flat field_values map to indexed array with metadata for persistence.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $flatValues = $data['field_values'] ?? [];
        $resourceId = $data['resource_id'] ?? '';
        $fieldConfigService = app(BookingFieldConfigServiceInterface::class);
        $configs = $fieldConfigService->getEnabledFields($resourceId);
        $configsByKey = [];
        foreach ($configs as $config) {
            $configsByKey[$config->key] = $config;
        }

        $structured = [];
        foreach ($flatValues as $key => $value) {
            if (isset($configsByKey[$key])) {
                $config = $configsByKey[$key];
                $structured[] = [
                    'field_key' => $key,
                    'field_label' => $config->label,
                    'value' => $value,
                    'visibility' => $config->visibility,
                ];
            }
        }

        $data['field_values'] = $structured;

        return $data;
    }

    /**
     * Created by BookingService, as on the website: the same overlap, schedule and settings
     * rules (they bind the staff too), the same lock, status and notifications.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $booking = app(BookingServiceInterface::class)->createBooking(CreateBookingDTO::fromArray($data));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->refuse($exception);
        }

        $record = BookingModel::query()->findOrFail($booking->id);

        // The service does not take the staff's notes
        if (($data['admin_notes'] ?? null) !== null) {
            $record->update(['admin_notes' => $data['admin_notes']]);
        }

        return $record;
    }

    /**
     * Show why the booking was refused, on the field it is about when there is one.
     */
    private function refuse(DomainException|InvalidArgumentException $exception): never
    {
        [$field, $key] = match (true) {
            $exception instanceof BookableResourceUnavailableException => ['resource_id', 'resource_unavailable'],
            $exception instanceof BookingLimitExceededException => ['user_id', 'limit_exceeded'],
            $exception instanceof TooFarToBookException => ['date', 'too_far'],
            $exception instanceof TooSoonToBookException => ['start_time', 'too_soon'],
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

        $message = __("venue-bookings::messages.create_errors.{$key}");

        if ($field === null) {
            Notification::make()->title($message)->danger()->send();

            throw new Halt;
        }

        throw ValidationException::withMessages(["data.{$field}" => $message]);
    }
}
