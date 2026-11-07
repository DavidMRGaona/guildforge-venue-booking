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
     * The form always sends its field configuration, so the settings template has to be
     * what the form starts from: a new space begins with the template and the person
     * creating it can adjust it before saving.
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        // Fill once with no state so every field takes its own default, then lay the template over it
        $this->form->fill();
        $this->form->fill(array_merge($this->data ?? [], [
            'field_config' => app(BookingFieldConfigServiceInterface::class)->getDefaultFieldConfig()->toArray(),
        ]));

        $this->callHook('afterFill');
    }
}
