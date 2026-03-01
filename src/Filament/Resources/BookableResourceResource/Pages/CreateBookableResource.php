<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;

final class CreateBookableResource extends CreateRecord
{
    protected static string $resource = BookableResourceResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('venue-bookings::messages.pages.create_resource');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! isset($data['field_config']) || $data['field_config'] === null) {
            $service = app(BookingFieldConfigServiceInterface::class);
            $data['field_config'] = $service->getDefaultFieldConfig()->toArray();
        }

        return $data;
    }
}
