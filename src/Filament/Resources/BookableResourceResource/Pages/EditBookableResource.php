<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;

final class EditBookableResource extends EditRecord
{
    protected static string $resource = BookableResourceResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('venue-bookings::messages.pages.edit_resource');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
