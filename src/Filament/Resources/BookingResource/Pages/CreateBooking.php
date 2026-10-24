<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookingResource\Pages;

use DomainException;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\VenueBookings\Application\DTOs\CreateBookingDTO;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\Concerns\ShowsBookingRefusals;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

final class CreateBooking extends CreateRecord
{
    use ShowsBookingRefusals;

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
     * Created by BookingService as on the website (overlaps, schedule, the same lock, status and
     * notifications), without the settings rules: the staff make the exception on purpose.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $booking = app(BookingServiceInterface::class)->createBookingAsStaff(CreateBookingDTO::fromArray($data));
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
}
