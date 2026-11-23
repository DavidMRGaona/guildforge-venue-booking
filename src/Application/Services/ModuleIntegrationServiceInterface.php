<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * What a booking may be linked to in other modules (game tables and campaigns), read only through
 * the core's contracts: the core's linkable record lookup to offer and check them, its activity
 * reader to name them on the public detail. Nothing here knows the game-tables module's classes.
 */
interface ModuleIntegrationServiceInterface
{
    public function isGameTablesModuleAvailable(): bool;

    public function isTournamentsModuleAvailable(): bool;

    /**
     * Game tables the viewer may see that match the query, the next ones first (for the team's
     * picker in the panel, and what a visitor is shown).
     *
     * @return list<array{id: string, label: string}>
     */
    public function getAvailableGameTables(?Authenticatable $viewer = null, string $query = ''): array;

    /**
     * @return list<array{id: string, label: string}>
     */
    public function getAvailableTournaments(): array;

    /**
     * Campaigns the viewer may see that match the query, under the same rule as getAvailableGameTables().
     *
     * @return list<array{id: string, label: string}>
     */
    public function getAvailableCampaigns(?Authenticatable $viewer = null, string $query = ''): array;

    /**
     * The game tables the signed-in person organizes (created or directs): the only ones they may
     * link their own booking to. Pass the request's user, never another account.
     *
     * @return list<array{id: string, label: string}>
     */
    public function getUserGameTables(Authenticatable $organizer): array;

    /**
     * The campaigns the signed-in person organizes, under the same rule as getUserGameTables().
     *
     * @return list<array{id: string, label: string}>
     */
    public function getUserCampaigns(Authenticatable $organizer): array;

    /**
     * The title of a game table as the viewer may see it through the linkable record lookup (the
     * panel's picker shows and checks a chosen table with it), or null when they may not.
     */
    public function getGameTableLabel(string $id, ?Authenticatable $viewer): ?string;

    /**
     * The title of a campaign, under the same rule as getGameTableLabel().
     */
    public function getCampaignLabel(string $id, ?Authenticatable $viewer): ?string;

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
