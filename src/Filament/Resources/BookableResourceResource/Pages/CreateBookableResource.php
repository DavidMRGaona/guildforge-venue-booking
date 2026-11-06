<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Filament\Resources\Pages\CreateRecord;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;

final class CreateBookableResource extends CreateRecord
{
    use HasKnowledgeGuideAction;

    protected static string $resource = BookableResourceResource::class;

    public function getTitle(): string
    {
        return __('venue-bookings::messages.pages.create_resource');
    }

    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction('venue-bookings.panel.spaces'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! isset($data['field_config'])) {
            $service = app(BookingFieldConfigServiceInterface::class);
            $data['field_config'] = $service->getDefaultFieldConfig()->toArray();
        }

        return $data;
    }
}
