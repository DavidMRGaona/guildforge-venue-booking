<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Services\TimezoneServiceInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;

/**
 * Optional integration with game-tables: every call checks the module is enabled first.
 * Its classes are absent when this module is analysed on its own (the CI's quality job),
 * hence the class.notFound ignores on the lines that use them.
 */
final readonly class ModuleIntegrationService implements ModuleIntegrationServiceInterface
{
    /** Activity source types of game-tables (a table, whatever its kind, is a «session») */
    private const string GAME_TABLE_SOURCE = 'game-tables.session';

    private const string CAMPAIGN_SOURCE = 'game-tables.campaign';

    public function isGameTablesModuleAvailable(): bool
    {
        return function_exists('module_enabled') && module_enabled('game-tables');
    }

    public function isTournamentsModuleAvailable(): bool
    {
        return function_exists('module_enabled') && module_enabled('tournaments');
    }

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getAvailableGameTables(): array
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return [];
        }

        try {
            $timezone = app(TimezoneServiceInterface::class);
            $queryService = app(GameTableQueryServiceInterface::class); // @phpstan-ignore class.notFound (game-tables is optional)
            $tables = $queryService->getUpcomingTables( // @phpstan-ignore class.notFound (game-tables is optional)
                new \DateTimeImmutable,
                $timezone->toDisplay(new \DateTimeImmutable)->modify('+30 days'),
            );

            return array_map(fn (GameTableListDTO $table): array => [ // @phpstan-ignore class.notFound (game-tables is optional)
                'id' => $table->id, // @phpstan-ignore class.notFound (game-tables is optional)
                'label' => $table->title, // @phpstan-ignore class.notFound (game-tables is optional)
            ], $tables);
        } catch (\Throwable $e) {
            $this->logFailure(__FUNCTION__, $e);

            return [];
        }
    }

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getAvailableTournaments(): array
    {
        if (! $this->isTournamentsModuleAvailable()) {
            return [];
        }

        // Tournaments module not yet implemented — return empty for now
        return [];
    }

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getAvailableCampaigns(): array
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return [];
        }

        try {
            $queryService = app(CampaignQueryServiceInterface::class); // @phpstan-ignore class.notFound (game-tables is optional)
            $campaigns = $queryService->getPublishedCampaignsPaginated(1, 100); // @phpstan-ignore class.notFound (game-tables is optional)

            return array_map(fn (CampaignResponseDTO $c): array => [ // @phpstan-ignore class.notFound (game-tables is optional)
                'id' => $c->id, // @phpstan-ignore class.notFound (game-tables is optional)
                'label' => $c->title, // @phpstan-ignore class.notFound (game-tables is optional)
            ], $campaigns);
        } catch (\Throwable $e) {
            $this->logFailure(__FUNCTION__, $e);

            return [];
        }
    }

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getUserGameTables(string $userId): array
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return [];
        }

        try {
            $model = app(GameTableModel::class); // @phpstan-ignore class.notFound (game-tables is optional)

            return $model->newQuery() // @phpstan-ignore class.notFound (game-tables is optional)
                ->where('created_by', $userId)
                ->where('is_published', true)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn ($t): array => ['id' => $t->id, 'label' => $t->title])
                ->all();
        } catch (\Throwable $e) {
            $this->logFailure(__FUNCTION__, $e);

            return [];
        }
    }

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getUserCampaigns(string $userId): array
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return [];
        }

        try {
            $model = app(CampaignModel::class); // @phpstan-ignore class.notFound (game-tables is optional)

            return $model->newQuery() // @phpstan-ignore class.notFound (game-tables is optional)
                ->where('created_by', $userId)
                ->where('is_published', true)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn ($c): array => ['id' => $c->id, 'label' => $c->title])
                ->all();
        } catch (\Throwable $e) {
            $this->logFailure(__FUNCTION__, $e);

            return [];
        }
    }

    public function getGameTableNameVisibleTo(string $id, ?Authenticatable $viewer): ?string
    {
        return $this->titleVisibleTo(self::GAME_TABLE_SOURCE, $id, $viewer, __FUNCTION__);
    }

    public function getCampaignNameVisibleTo(string $id, ?Authenticatable $viewer): ?string
    {
        return $this->titleVisibleTo(self::CAMPAIGN_SOURCE, $id, $viewer, __FUNCTION__);
    }

    /**
     * Asks the module that owns the record, through the core's activity reader, how it shows the
     * record to this viewer: it answers with its own access rule (published, not private), so
     * nothing here reads the module's tables. Whatever is not a clear answer is no name.
     */
    private function titleVisibleTo(string $sourceType, string $id, ?Authenticatable $viewer, string $method): ?string
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return null;
        }

        try {
            $described = app(ActivityReaderInterface::class)->describe($sourceType, [$id], $viewer);

            return $described->isRead() ? $described->find(strtolower($id))?->title : null;
        } catch (\Throwable $e) {
            $this->logFailure($method, $e);

            return null;
        }
    }

    /**
     * A failure leaves the selector empty, so it is logged: a change in game-tables would
     * otherwise go unnoticed.
     */
    private function logFailure(string $method, \Throwable $e): void
    {
        Log::warning("[venue-bookings] game-tables integration failed in {$method}", [
            'exception' => $e::class,
            'message' => $e->getMessage(),
        ]);
    }
}
