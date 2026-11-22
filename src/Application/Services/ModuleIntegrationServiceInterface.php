<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Illuminate\Contracts\Auth\Authenticatable;

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

    /**
     * The title of a game table, only when the game-tables module shows it to this viewer (through
     * the core's activity contract): a draft, a private or an unknown one has no name.
     */
    public function getGameTableNameVisibleTo(string $id, ?Authenticatable $viewer): ?string;

    /**
     * The title of a campaign, under the same rule as getGameTableNameVisibleTo().
     */
    public function getCampaignNameVisibleTo(string $id, ?Authenticatable $viewer): ?string;
}
