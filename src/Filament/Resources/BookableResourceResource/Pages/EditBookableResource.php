<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;

final class EditBookableResource extends EditRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = BookableResourceResource::class;

    public function getTitle(): string
    {
        return __('venue-bookings::messages.pages.edit_resource');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            DeleteAction::make(),
            $this->knowledgeGuideAction('venue-bookings.panel.spaces'),
        ]);
    }
}
