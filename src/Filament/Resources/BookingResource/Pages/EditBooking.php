<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookingResource\Pages;

use DomainException;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Modules\VenueBookings\Application\DTOs\CancelBookingDTO;
use Modules\VenueBookings\Application\DTOs\MoveBookingDTO;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingServiceInterface;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\Concerns\ShowsBookingRefusals;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

final class EditBooking extends EditRecord
{
    use ShowsBookingRefusals;

    protected static string $resource = BookingResource::class;

    // BookingService saves a move under its lock; a transaction around it would commit after
    // the lock is released, and another request could miss the move in its overlap check
    protected ?bool $hasDatabaseTransactions = false;

    public function getTitle(): string|Htmlable
    {
        return __('venue-bookings::messages.pages.edit_booking');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Flatten the indexed field_values array into a key-value map for the form.
     *
     * DB: [{"field_key": "activity_name", "value": "Chess", ...}, ...]
     * Form: {"activity_name": "Chess", ...}
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $storedValues = $data['field_values'] ?? [];
        $flat = [];

        if (is_array($storedValues)) {
            foreach ($storedValues as $entry) {
                if (is_array($entry) && isset($entry['field_key'])) {
                    $flat[$entry['field_key']] = $entry['value'] ?? null;
                }
            }
        }

        $data['field_values'] = $flat;

        return $data;
    }

    /**
     * Convert flat field_values map back to indexed array with metadata for persistence.
     *
     * Form: {"activity_name": "Chess", ...}
     * DB: [{"field_key": "activity_name", "field_label": "...", "value": "Chess", "visibility": "..."}, ...]
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['field_values'] = $this->buildStructuredFieldValues($data['field_values'] ?? []);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $flatValues
     * @return array<array{field_key: string, field_label: string, value: mixed, visibility: string}>
     */
    private function buildStructuredFieldValues(array $flatValues): array
    {
        /** @var BookingModel $record */
        $record = $this->record;
        $fieldConfigService = app(BookingFieldConfigServiceInterface::class);
        $configs = $fieldConfigService->getEnabledFields($record->resource_id);
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

        return $structured;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BookingModel $record */
        // Moved first, so a refused move leaves the booking as it was
        if ($this->movesBooking($record, $data)) {
            try {
                app(BookingServiceInterface::class)->moveBookingAsStaff(new MoveBookingDTO(
                    bookingId: $record->id,
                    resourceId: (string) $data['resource_id'],
                    date: (string) $data['date'],
                    startTime: (string) $data['start_time'],
                    endTime: (string) $data['end_time'],
                ));
            } catch (DomainException|InvalidArgumentException $exception) {
                $this->refuse($exception);
            }

            $record->refresh();
        }

        unset($data['resource_id'], $data['date'], $data['start_time'], $data['end_time']);

        $newStatusValue = $data['status'] ?? null;
        $oldStatus = $record->status;
        $newStatus = $newStatusValue !== null ? BookingStatus::from($newStatusValue) : $oldStatus;

        if ($oldStatus !== $newStatus) {
            // The status select only offers these to users who may apply them; a crafted request must not
            $ability = match ($newStatus) {
                BookingStatus::Confirmed => 'confirm',
                BookingStatus::Cancelled => 'cancel',
                BookingStatus::Rejected => 'reject',
                BookingStatus::NoShow => 'markNoShow',
                default => 'update',
            };
            Gate::authorize($ability, $record);

            try {
                $service = app(BookingServiceInterface::class);

                match ($newStatus) {
                    BookingStatus::Confirmed => $service->confirmBooking($record->id),
                    BookingStatus::Cancelled => $service->cancelBookingAsStaff(new CancelBookingDTO(
                        bookingId: $record->id,
                        userId: (string) Auth::id(),
                        reason: $data['cancellation_reason'] ?? null,
                    )),
                    BookingStatus::Rejected => $service->rejectBooking(
                        $record->id,
                        $data['cancellation_reason'] ?? null,
                    ),
                    BookingStatus::Completed => $service->markCompleted($record->id),
                    BookingStatus::NoShow => $service->markNoShow($record->id),
                    default => null,
                };

                unset($data['status'], $data['cancellation_reason']);
                $record->refresh();
            } catch (InvalidStatusTransitionException $e) {
                Notification::make()
                    ->title(__('venue-bookings::messages.notifications.invalid_transition'))
                    ->danger()
                    ->send();

                $this->halt();
            }
        }

        $record->update($data);

        return $record;
    }

    /**
     * Whether the form changes the resource, the date or the times (seconds ignored).
     *
     * @param  array<string, mixed>  $data
     */
    private function movesBooking(BookingModel $record, array $data): bool
    {
        $time = static fn (mixed $value): string => substr(trim((string) $value), 0, 5);

        return strtolower((string) ($data['resource_id'] ?? '')) !== strtolower($record->resource_id)
            || (string) ($data['date'] ?? '') !== $record->date->format('Y-m-d')
            || $time($data['start_time'] ?? '') !== $time($record->start_time)
            || $time($data['end_time'] ?? '') !== $time($record->end_time);
    }
}
