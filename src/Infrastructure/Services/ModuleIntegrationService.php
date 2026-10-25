<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

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
            $queryService = app(GameTableQueryServiceInterface::class); // @phpstan-ignore class.notFound (game-tables is optional)
            $tables = $queryService->getUpcomingTables( // @phpstan-ignore class.notFound (game-tables is optional)
                new \DateTimeImmutable,
                new \DateTimeImmutable('+30 days'),
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

    public function getGameTableName(string $id): ?string
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return null;
        }

        try {
            $model = app(GameTableModel::class); // @phpstan-ignore class.notFound (game-tables is optional)
            $table = $model->newQuery()->find($id); // @phpstan-ignore class.notFound (game-tables is optional)

            return $table?->title;
        } catch (\Throwable $e) {
            $this->logFailure(__FUNCTION__, $e);

            return null;
        }
    }

    public function getCampaignName(string $id): ?string
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return null;
        }

        try {
            $model = app(CampaignModel::class); // @phpstan-ignore class.notFound (game-tables is optional)
            $campaign = $model->newQuery()->find($id); // @phpstan-ignore class.notFound (game-tables is optional)

            return $campaign?->title;
        } catch (\Throwable $e) {
            $this->logFailure(__FUNCTION__, $e);

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
