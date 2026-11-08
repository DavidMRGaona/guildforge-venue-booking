<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament;

use App\Filament\Actions\RowDetailsAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\ListBookableResources;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\ListBookings;
use Modules\VenueBookings\Filament\Widgets\UpcomingBookingsWidget;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The panel tables overflowed their container (693 px at 375 px, 156 px at 1280 px) and pushed
 * the row actions out of sight: the row actions sit in one menu and the secondary columns
 * appear only from the width where they fit.
 */
final class PanelTablesFitTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookingResource::class, BookableResourceResource::class]);
        $this->actingAsPanelAdmin();
    }

    public function test_the_bookings_row_actions_sit_in_one_menu(): void
    {
        $actions = $this->table(ListBookings::class)->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
    }

    public function test_the_bookings_menu_starts_with_the_row_details(): void
    {
        $group = $this->table(ListBookings::class)->getRecordActions()[0];

        $this->assertInstanceOf(ActionGroup::class, $group);
        $this->assertSame(RowDetailsAction::NAME, array_key_first($group->getFlatActions()));
        $this->assertNotNull($this->table(ListBookings::class)->getAction(RowDetailsAction::NAME));
    }

    public function test_the_spaces_edit_action_and_row_details_share_one_menu(): void
    {
        $actions = $this->table(ListBookableResources::class)->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(ActionGroup::class, $actions[0]);
        $this->assertSame([RowDetailsAction::NAME, 'edit'], array_keys($actions[0]->getFlatActions()));
    }

    public function test_the_upcoming_widget_offers_row_details_as_an_icon(): void
    {
        $actions = $this->table(UpcomingBookingsWidget::class)->getRecordActions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(Action::class, $actions[0]);
        $this->assertSame(RowDetailsAction::NAME, $actions[0]->getName());
        $this->assertTrue($actions[0]->isIconButton());
    }

    public function test_the_space_counters_are_centred(): void
    {
        foreach (['capacity', 'bookings_count'] as $name) {
            $this->assertSame(Alignment::Center, $this->table(ListBookableResources::class)->getColumn($name)?->getAlignment());
        }
    }

    /**
     * @return array<string, array{class-string<HasTable>, string, ?string}>
     */
    public static function columnBreakpoints(): array
    {
        return [
            'bookings: space' => [ListBookings::class, 'resource.name', 'sm'],
            'bookings: date' => [ListBookings::class, 'date', 'sm'],
            'bookings: status always shown' => [ListBookings::class, 'status', null],
            'bookings: start time' => [ListBookings::class, 'start_time', 'sm'],
            'bookings: end time' => [ListBookings::class, 'end_time', 'md'],
            'bookings: user' => [ListBookings::class, 'user.name', 'xl'],
            'spaces: name always shown' => [ListBookableResources::class, 'name', null],
            'spaces: status always shown' => [ListBookableResources::class, 'status', null],
            'spaces: capacity' => [ListBookableResources::class, 'capacity', 'sm'],
            'spaces: bookings count' => [ListBookableResources::class, 'bookings_count', 'md'],
            'upcoming widget: space always shown' => [UpcomingBookingsWidget::class, 'resource.name', null],
            'upcoming widget: status always shown' => [UpcomingBookingsWidget::class, 'status', null],
            'upcoming widget: start time' => [UpcomingBookingsWidget::class, 'start_time', 'sm'],
            'upcoming widget: end time' => [UpcomingBookingsWidget::class, 'end_time', 'md'],
            'upcoming widget: user' => [UpcomingBookingsWidget::class, 'user.name', 'lg'],
        ];
    }

    /**
     * @param  class-string<HasTable>  $component
     */
    #[DataProvider('columnBreakpoints')]
    public function test_secondary_columns_appear_from_the_width_where_they_fit(string $component, string $column, ?string $breakpoint): void
    {
        $this->assertSame($breakpoint, $this->table($component)->getColumn($column)?->getVisibleFrom());
    }

    public function test_the_upcoming_widget_wraps_long_space_names_instead_of_widening(): void
    {
        $column = $this->table(UpcomingBookingsWidget::class)->getColumn('resource.name');

        $this->assertInstanceOf(TextColumn::class, $column);
        $this->assertTrue($column->canWrap());
    }

    /**
     * Space, date and times do not fit side by side on a phone: one column stacks them there
     * and gives way to the separate columns from 640 px.
     */
    public function test_a_phone_shows_the_booking_in_one_stacked_column(): void
    {
        $summary = $this->table(ListBookings::class)->getColumn('summary');

        $this->assertNotNull($summary);
        $this->assertSame('sm', $summary->getHiddenFrom());
        $this->assertNull($summary->getVisibleFrom());
    }

    /**
     * @param  class-string<HasTable>  $component
     */
    private function table(string $component): Table
    {
        $instance = Livewire::test($component)->instance();
        $this->assertInstanceOf(HasTable::class, $instance);

        return $instance->getTable();
    }
}
