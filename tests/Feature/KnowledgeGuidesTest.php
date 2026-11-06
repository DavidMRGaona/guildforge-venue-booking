<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature;

use Modules\VenueBookings\Filament\Pages\VenueBookingsSettings;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's help guides pass the knowledge base lint, so CI blocks a broken one.
 */
final class KnowledgeGuidesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    public function test_guides_are_valid(): void
    {
        // The panel guides link to the module's panel pages, routed only once they are on the panel
        $this->registerOnAdminPanel(
            [BookableResourceResource::class, BookingResource::class],
            [VenueBookingsSettings::class],
        );

        $this->assertModuleKnowledgeGuidesAreValid('venue-bookings');
    }
}
