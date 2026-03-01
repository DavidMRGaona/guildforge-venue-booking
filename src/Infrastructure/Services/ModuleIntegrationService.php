<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;

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
            /** @var \Modules\GameTables\Application\Services\GameTableQueryServiceInterface $queryService */
            $queryService = app(\Modules\GameTables\Application\Services\GameTableQueryServiceInterface::class);
            $tables = $queryService->getUpcomingTables(
                new \DateTimeImmutable,
                new \DateTimeImmutable('+30 days'),
            );

            return array_map(fn (object $table): array => [
                'id' => $table->id,
                'label' => $table->title,
            ], $tables->all());
        } catch (\Throwable) {
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
            /** @var \Modules\GameTables\Application\Services\CampaignQueryServiceInterface $queryService */
            $queryService = app(\Modules\GameTables\Application\Services\CampaignQueryServiceInterface::class);
            $campaigns = $queryService->getPublishedCampaignsPaginated(1, 100);

            return array_map(fn (object $c): array => [
                'id' => $c->id,
                'label' => $c->title,
            ], $campaigns);
        } catch (\Throwable) {
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
            /** @var \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel $model */
            $model = app(\Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel::class);

            return $model->newQuery()
                ->where('created_by', $userId)
                ->where('is_published', true)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn ($t): array => ['id' => $t->id, 'label' => $t->title])
                ->all();
        } catch (\Throwable) {
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
            /** @var \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel $model */
            $model = app(\Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel::class);

            return $model->newQuery()
                ->where('created_by', $userId)
                ->where('is_published', true)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(fn ($c): array => ['id' => $c->id, 'label' => $c->title])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function getGameTableName(string $id): ?string
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return null;
        }

        try {
            /** @var \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel $model */
            $model = app(\Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel::class);
            $table = $model->newQuery()->find($id);

            return $table?->title;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getCampaignName(string $id): ?string
    {
        if (! $this->isGameTablesModuleAvailable()) {
            return null;
        }

        try {
            /** @var \Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel $model */
            $model = app(\Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel::class);
            $campaign = $model->newQuery()->find($id);

            return $campaign?->title;
        } catch (\Throwable) {
            return null;
        }
    }
}
