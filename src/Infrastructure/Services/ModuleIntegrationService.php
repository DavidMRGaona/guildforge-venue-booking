<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;

/**
 * Optional integration with game-tables, only through the core's contracts: the linkable record
 * lookup offers and checks the tables and campaigns (types `game-table` and `campaign`), the
 * activity reader names them on the public detail. Both fail closed, so with game-tables
 * disabled, missing or failing nothing is offered and nothing is named.
 *
 * Bound scoped: the types offered and what each person organizes are read once per request or
 * job (the booking form asks for every space, and saving builds the fields twice).
 */
final class ModuleIntegrationService implements ModuleIntegrationServiceInterface
{
    /** Linkable record types game-tables offers */
    private const string GAME_TABLE_TYPE = 'game-table';

    private const string CAMPAIGN_TYPE = 'campaign';

    /** Activity source types of game-tables (a table, whatever its kind, is a «session») */
    private const string GAME_TABLE_SOURCE = 'game-tables.session';

    private const string CAMPAIGN_SOURCE = 'game-tables.campaign';

    /** How many tables or campaigns a select offers at most */
    private const int OFFERED = LinkableRecordLookupInterface::MAX_SEARCH_LIMIT;

    /** @var array<string, string>|null Linkable record types offered right now, read once */
    private ?array $types = null;

    /** @var array<string, list<array{id: string, label: string}>> What each person organizes, by type and account */
    private array $organized = [];

    public function __construct(
        private readonly LinkableRecordLookupInterface $linkableRecords,
        private readonly ActivityReaderInterface $activity,
    ) {}

    public function isGameTablesModuleAvailable(): bool
    {
        return function_exists('module_enabled') && module_enabled('game-tables');
    }

    public function offersGameTables(): bool
    {
        return $this->offers(self::GAME_TABLE_TYPE);
    }

    public function offersCampaigns(): bool
    {
        return $this->offers(self::CAMPAIGN_TYPE);
    }

    public function isTournamentsModuleAvailable(): bool
    {
        return function_exists('module_enabled') && module_enabled('tournaments');
    }

    public function getAvailableGameTables(?Authenticatable $viewer = null, string $query = ''): array
    {
        return $this->search(self::GAME_TABLE_TYPE, $query, $viewer);
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public function getAvailableTournaments(): array
    {
        if (! $this->isTournamentsModuleAvailable()) {
            return [];
        }

        // Tournaments module not yet implemented — return empty for now
        return [];
    }

    public function getAvailableCampaigns(?Authenticatable $viewer = null, string $query = ''): array
    {
        return $this->search(self::CAMPAIGN_TYPE, $query, $viewer);
    }

    public function getUserGameTables(Authenticatable $organizer): array
    {
        return $this->organizedBy(self::GAME_TABLE_TYPE, $organizer);
    }

    public function getUserCampaigns(Authenticatable $organizer): array
    {
        return $this->organizedBy(self::CAMPAIGN_TYPE, $organizer);
    }

    public function getGameTableLabel(string $id, ?Authenticatable $viewer): ?string
    {
        return $this->label(self::GAME_TABLE_TYPE, $id, $viewer);
    }

    public function getCampaignLabel(string $id, ?Authenticatable $viewer): ?string
    {
        return $this->label(self::CAMPAIGN_TYPE, $id, $viewer);
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
     * @return list<array{id: string, label: string}>
     */
    private function search(string $type, string $query, ?Authenticatable $viewer): array
    {
        if (! $this->offers($type)) {
            return [];
        }

        return $this->options($this->linkableRecords->search($type, $query, $this->viewerId($viewer), self::OFFERED));
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function organizedBy(string $type, Authenticatable $organizer): array
    {
        if (! $this->offers($type)) {
            return [];
        }

        $key = $type.'|'.$this->viewerId($organizer);

        return $this->organized[$key] ??= $this->options($this->linkableRecords->organizedBy($type, $organizer, self::OFFERED));
    }

    private function label(string $type, string $id, ?Authenticatable $viewer): ?string
    {
        if (! $this->offers($type)) {
            return null;
        }

        return $this->linkableRecords->find($type, $id, $this->viewerId($viewer))?->title;
    }

    /**
     * @param  list<LinkableRecordDTO>  $records
     * @return list<array{id: string, label: string}>
     */
    private function options(array $records): array
    {
        return array_map(
            static fn (LinkableRecordDTO $record): array => ['id' => $record->id, 'label' => $record->title],
            $records,
        );
    }

    private function offers(string $type): bool
    {
        $this->types ??= $this->linkableRecords->types();

        return isset($this->types[$type]);
    }

    private function viewerId(?Authenticatable $viewer): string
    {
        $id = $viewer?->getAuthIdentifier();

        return is_string($id) ? $id : LinkableRecordSourceInterface::VISITOR;
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
            $described = $this->activity->describe($sourceType, [$id], $viewer);

            return $described->isRead() ? $described->find(strtolower($id))?->title : null;
        } catch (\Throwable $e) {
            Log::warning("[venue-bookings] game-tables integration failed in {$method}", [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
