<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Resources\Pages\EditRecord;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;

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
            $this->deleteAction(),
            $this->knowledgeGuideAction('venue-bookings.panel.spaces'),
        ]);
    }

    /**
     * Deleting a space also deletes all its bookings, past and future, and cannot be undone:
     * the modal says how many go and asks for an explicit second confirmation.
     */
    private function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->requiresConfirmation()
            ->modalDescription(fn (BookableResourceModel $record): string => $this->deleteConsequence($record))
            ->schema([
                Checkbox::make('confirmation')
                    ->label(__('venue-bookings::messages.actions.delete_resource_confirmation_check'))
                    ->accepted()
                    ->required(),
            ]);
    }

    private function deleteConsequence(BookableResourceModel $resource): string
    {
        $total = $resource->bookings()->count();

        $description = trans_choice('venue-bookings::messages.actions.delete_resource_bookings', $total, ['count' => $total]);

        if ($total === 0) {
            return $description;
        }

        $today = CarbonImmutable::now(app(BookingSettingsReader::class)->getDateTimeZone())->toDateString();
        $upcoming = $resource->bookings()->where('date', '>=', $today)->count();

        if ($upcoming > 0) {
            $description .= ' '.trans_choice('venue-bookings::messages.actions.delete_resource_upcoming', $upcoming, ['count' => $upcoming]);
        }

        return $description.' '.__('venue-bookings::messages.actions.delete_resource_irreversible');
    }
}
