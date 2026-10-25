<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Infrastructure\Services;

use App\Application\Modules\Services\ModuleContextServiceInterface;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Domain\Enums\TableFormat;
use Modules\GameTables\Domain\Enums\TableStatus;
use Modules\GameTables\Domain\Enums\TableType;
use Modules\VenueBookings\Infrastructure\Services\ModuleIntegrationService;
use Tests\TestCase;

final class ModuleIntegrationServiceTest extends TestCase
{
    public function test_available_game_tables_lists_the_upcoming_tables(): void
    {
        // The game-tables module is optional: it is not installed next to this one in CI
        if (! interface_exists(GameTableQueryServiceInterface::class)) {
            $this->markTestSkipped('The game-tables module is not installed.');
        }

        $modules = $this->createStub(ModuleContextServiceInterface::class);
        $modules->method('isEnabled')->willReturnCallback(fn (string $name): bool => $name === 'game-tables');
        $this->app->instance(ModuleContextServiceInterface::class, $modules);

        $queryService = $this->createStub(GameTableQueryServiceInterface::class);
        $queryService->method('getUpcomingTables')->willReturn([
            $this->gameTable('table-1', 'La tumba de los horrores'),
            $this->gameTable('table-2', 'Kill Team'),
        ]);
        $this->app->instance(GameTableQueryServiceInterface::class, $queryService);

        $this->assertSame([
            ['id' => 'table-1', 'label' => 'La tumba de los horrores'],
            ['id' => 'table-2', 'label' => 'Kill Team'],
        ], (new ModuleIntegrationService)->getAvailableGameTables());
    }

    private function gameTable(string $id, string $title): GameTableListDTO
    {
        return new GameTableListDTO(
            id: $id,
            title: $title,
            slug: null,
            gameSystemName: 'D&D 5e',
            startsAt: null,
            durationMinutes: 180,
            tableFormat: TableFormat::cases()[0],
            tableType: TableType::cases()[0],
            status: TableStatus::cases()[0],
            location: null,
            onlineUrl: null,
            minPlayers: 2,
            maxPlayers: 5,
            currentPlayers: 0,
            isPublished: true,
            creatorName: 'John Doe',
            mainGameMasterName: 'John Doe',
            eventId: null,
            eventTitle: null,
            imagePublicId: null,
        );
    }
}
