<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

interface ModuleIntegrationServiceInterface
{
    public function isGameTablesModuleAvailable(): bool;

    public function isTournamentsModuleAvailable(): bool;

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getAvailableGameTables(): array;

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getAvailableTournaments(): array;

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getAvailableCampaigns(): array;

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getUserGameTables(string $userId): array;

    /**
     * @return array<array{id: string, label: string}>
     */
    public function getUserCampaigns(string $userId): array;

    public function getGameTableName(string $id): ?string;

    public function getCampaignName(string $id): ?string;
}
