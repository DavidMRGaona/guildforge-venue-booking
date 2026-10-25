<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Infrastructure\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\GameTables\Application\DTOs\CampaignResponseDTO;
use Modules\GameTables\Application\DTOs\GameTableListDTO;
use Modules\GameTables\Application\Services\CampaignQueryServiceInterface;
use Modules\GameTables\Application\Services\GameTableQueryServiceInterface;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\CampaignModel;
use Modules\GameTables\Infrastructure\Persistence\Eloquent\Models\GameTableModel;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * What ModuleIntegrationService uses from game-tables. PHPStan cannot check it (game-tables is
 * optional and absent when this module is analysed on its own) and the service turns any
 * failure into an empty list, so a rename in game-tables would only show as empty selectors.
 * Runs wherever game-tables is installed next to this module.
 */
final class GameTablesIntegrationContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! interface_exists(GameTableQueryServiceInterface::class)) {
            $this->markTestSkipped('The game-tables module is not installed.');
        }
    }

    public function test_the_query_services_offer_the_methods_the_integration_calls(): void
    {
        $this->assertReturnsArray(GameTableQueryServiceInterface::class, 'getUpcomingTables'); // @phpstan-ignore class.notFound (game-tables is optional)
        $this->assertReturnsArray(CampaignQueryServiceInterface::class, 'getPublishedCampaignsPaginated'); // @phpstan-ignore class.notFound (game-tables is optional)
    }

    /**
     * @param  class-string  $dto
     */
    #[DataProvider('dtos')]
    public function test_the_dtos_expose_an_id_and_a_title(string $dto): void
    {
        $class = new ReflectionClass($dto);

        foreach (['id', 'title'] as $property) {
            $this->assertTrue($class->hasProperty($property), "{$dto}::\${$property}");
            $this->assertTrue($class->getProperty($property)->isPublic(), "{$dto}::\${$property} is public");
        }
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('models')]
    public function test_the_models_have_the_columns_the_integration_queries(string $model): void
    {
        $fillable = (new $model)->getFillable();

        foreach (['title', 'created_by', 'is_published'] as $column) {
            $this->assertContains($column, $fillable, "{$model} {$column}");
        }
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function dtos(): array
    {
        return [
            'game table' => [GameTableListDTO::class], // @phpstan-ignore class.notFound (game-tables is optional)
            'campaign' => [CampaignResponseDTO::class], // @phpstan-ignore class.notFound (game-tables is optional)
        ];
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function models(): array
    {
        return [
            'game table' => [GameTableModel::class], // @phpstan-ignore class.notFound (game-tables is optional)
            'campaign' => [CampaignModel::class], // @phpstan-ignore class.notFound (game-tables is optional)
        ];
    }

    /**
     * @param  class-string  $interface
     */
    private function assertReturnsArray(string $interface, string $method): void
    {
        $class = new ReflectionClass($interface);
        $this->assertTrue($class->hasMethod($method), "{$interface}::{$method}()");

        $type = $class->getMethod($method)->getReturnType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);
        $this->assertSame('array', $type->getName(), "{$interface}::{$method}() returns an array");
    }
}
