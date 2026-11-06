<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;

final class ListBookableResources extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = BookableResourceResource::class;

    public function getTitle(): string
    {
        return __('venue-bookings::messages.model.bookable_resource.plural');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            CreateAction::make()
                ->label(__('venue-bookings::messages.pages.create_resource')),
            $this->knowledgeGuideAction('venue-bookings.panel.spaces'),
        ]);
    }
}
