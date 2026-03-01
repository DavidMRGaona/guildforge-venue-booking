<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Filament\Resources\BookingResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Modules\VenueBookings\Domain\Enums\BookingStatus;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;

final class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('venue-bookings::messages.model.booking.plural');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('venue-bookings::messages.pages.create_booking')),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('venue-bookings::messages.tabs.all')),

            'pending' => Tab::make(__('venue-bookings::messages.tabs.pending'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', BookingStatus::Pending->value))
                ->badge($this->getStatusCount(BookingStatus::Pending))
                ->badgeColor('warning')
                ->icon('heroicon-o-clock'),

            'confirmed' => Tab::make(__('venue-bookings::messages.tabs.confirmed'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', BookingStatus::Confirmed->value))
                ->icon('heroicon-o-check-circle'),

            'cancelled' => Tab::make(__('venue-bookings::messages.tabs.cancelled'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    BookingStatus::Cancelled->value,
                    BookingStatus::Rejected->value,
                ]))
                ->icon('heroicon-o-x-circle'),
        ];
    }

    private function getStatusCount(BookingStatus $status): int
    {
        return BookingModel::query()
            ->where('status', $status->value)
            ->count();
    }
}
