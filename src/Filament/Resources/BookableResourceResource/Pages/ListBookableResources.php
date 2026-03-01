<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;

final class ListBookableResources extends ListRecords
{
    protected static string $resource = BookableResourceResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('venue-bookings::messages.model.bookable_resource.plural');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('venue-bookings::messages.pages.create_resource')),
        ];
    }
}
