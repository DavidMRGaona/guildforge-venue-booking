<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Infrastructure\Services;

use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Linking\Contracts\LinkableRecordSourceInterface;
use App\Application\Linking\DTOs\LinkableRecordDTO;
use App\Application\Linking\Services\LinkableRecordLookupInterface;
use App\Application\Modules\Services\ModuleContextServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Infrastructure\Services\ModuleIntegrationService;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * Game tables and campaigns are read only through the core's linkable record lookup (types
 * `game-table` and `campaign`): nothing here knows the game-tables module's classes.
 */
final class ModuleIntegrationServiceTest extends TestCase
{
    private const string VIEWER = '0199f0a0-0000-7000-8000-0000000000aa';

    private LinkableRecordLookupInterface&MockObject $lookup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableGameTables(true);
        $this->lookup = $this->createMock(LinkableRecordLookupInterface::class);
    }

    public function test_the_team_is_offered_the_tables_and_campaigns_it_may_see(): void
    {
        $viewer = $this->account(self::VIEWER);
        $this->lookup->expects($this->exactly(2))->method('search')->willReturnCallback(
            fn (string $type, string $query, string $viewerId, int $limit): array => match ([$type, $query, $viewerId, $limit]) {
                ['game-table', 'tumba', self::VIEWER, LinkableRecordLookupInterface::MAX_SEARCH_LIMIT] => [$this->record('game-table', 'table-1', 'La tumba de los horrores')],
                ['campaign', '', self::VIEWER, LinkableRecordLookupInterface::MAX_SEARCH_LIMIT] => [$this->record('campaign', 'campaign-1', 'La maldición de Strahd')],
                default => $this->fail("Unexpected search for {$type}"),
            },
        );

        $this->assertSame([['id' => 'table-1', 'label' => 'La tumba de los horrores']], $this->service()->getAvailableGameTables($viewer, 'tumba'));
        $this->assertSame([['id' => 'campaign-1', 'label' => 'La maldición de Strahd']], $this->service()->getAvailableCampaigns($viewer));
    }

    public function test_a_visitor_is_asked_for_as_one(): void
    {
        $this->lookup->expects($this->once())->method('search')
            ->with('game-table', '', LinkableRecordSourceInterface::VISITOR, LinkableRecordLookupInterface::MAX_SEARCH_LIMIT)
            ->willReturn([]);

        $this->assertSame([], $this->service()->getAvailableGameTables());
    }

    public function test_a_person_is_offered_only_what_they_organize_asked_with_their_own_account(): void
    {
        $organizer = $this->account(self::VIEWER);
        $this->lookup->expects($this->exactly(2))->method('organizedBy')->willReturnCallback(
            fn (string $type, UserModel $who, int $limit): array => $who === $organizer && $limit === LinkableRecordLookupInterface::MAX_SEARCH_LIMIT
                ? [$this->record($type, "{$type}-mine", "Lo mío ({$type})")]
                : $this->fail('Asked about another account'),
        );
        $this->lookup->expects($this->never())->method('search');

        $this->assertSame([['id' => 'game-table-mine', 'label' => 'Lo mío (game-table)']], $this->service()->getUserGameTables($organizer));
        $this->assertSame([['id' => 'campaign-mine', 'label' => 'Lo mío (campaign)']], $this->service()->getUserCampaigns($organizer));
    }

    public function test_a_chosen_table_or_campaign_is_named_as_the_viewer_may_see_it(): void
    {
        $this->lookup->expects($this->exactly(4))->method('find')->willReturnCallback(
            fn (string $type, string $id, string $viewerId): ?LinkableRecordDTO => $viewerId === self::VIEWER && $id === 'seen'
                ? $this->record($type, $id, "Título de {$type}")
                : null,
        );

        $this->assertSame('Título de game-table', $this->service()->getGameTableLabel('seen', $this->account(self::VIEWER)));
        $this->assertSame('Título de campaign', $this->service()->getCampaignLabel('seen', $this->account(self::VIEWER)));
        $this->assertNull($this->service()->getGameTableLabel('seen', null));
        $this->assertNull($this->service()->getCampaignLabel('hidden', $this->account(self::VIEWER)));
    }

    public function test_nothing_is_asked_while_game_tables_is_disabled(): void
    {
        $this->enableGameTables(false);
        $this->lookup->expects($this->never())->method($this->anything());

        $this->assertSame([], $this->service()->getAvailableGameTables($this->account(self::VIEWER)));
        $this->assertSame([], $this->service()->getUserCampaigns($this->account(self::VIEWER)));
        $this->assertNull($this->service()->getGameTableLabel('any', null));
    }

    private function service(): ModuleIntegrationService
    {
        return new ModuleIntegrationService($this->lookup, $this->createStub(ActivityReaderInterface::class));
    }

    private function enableGameTables(bool $enabled): void
    {
        $modules = $this->createStub(ModuleContextServiceInterface::class);
        $modules->method('isEnabled')->willReturnCallback(fn (string $name): bool => $enabled && $name === 'game-tables');
        $this->app->instance(ModuleContextServiceInterface::class, $modules);
    }

    private function account(string $id): UserModel
    {
        $account = new UserModel;
        $account->id = $id;

        return $account;
    }

    private function record(string $type, string $id, string $title): LinkableRecordDTO
    {
        return new LinkableRecordDTO(type: $type, id: $id, title: $title);
    }
}
