<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Infrastructure\Services;

use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Enums\BookableResourceStatus;
use Modules\VenueBookings\Domain\Enums\BookingFieldType;
use Modules\VenueBookings\Domain\Enums\FieldVisibility;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\ResourceFieldConfig;
use Modules\VenueBookings\Infrastructure\Services\BookingFieldConfigService;
use PHPUnit\Framework\MockObject\Stub;
use Tests\TestCase;

final class BookingFieldConfigServiceTest extends TestCase
{
    private ModuleIntegrationServiceInterface&Stub $moduleIntegration;

    private BookableResourceRepositoryInterface&Stub $resourceRepository;

    private BookingFieldConfigService $service;

    private string $resourceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleIntegration = $this->createStub(ModuleIntegrationServiceInterface::class);
        $this->moduleIntegration->method('isGameTablesModuleAvailable')->willReturn(false);
        $this->moduleIntegration->method('isTournamentsModuleAvailable')->willReturn(false);

        $this->resourceRepository = $this->createStub(BookableResourceRepositoryInterface::class);

        $this->service = new BookingFieldConfigService($this->moduleIntegration, $this->resourceRepository);

        $this->resourceId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    }

    protected function tearDown(): void
    {
        config()->offsetUnset('modules.settings.venue-bookings');
        parent::tearDown();
    }

    public function test_get_enabled_fields_uses_resource_field_config(): void
    {
        $this->mockResourceWithFieldConfig([
            'predefined_fields' => [
                'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
                'num_participants' => ['enabled' => true, 'required' => false, 'visibility' => 'authenticated'],
            ],
            'custom_fields' => [],
        ]);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(2, $fields);
        $this->assertSame('activity_name', $fields[0]->key);
        $this->assertSame(BookingFieldType::Text->value, $fields[0]->type);
        $this->assertSame('num_participants', $fields[1]->key);
        $this->assertSame(BookingFieldType::Number->value, $fields[1]->type);
    }

    public function test_get_enabled_fields_returns_empty_when_all_disabled(): void
    {
        $this->mockResourceWithFieldConfig([
            'predefined_fields' => [
                'activity_name' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
                'notes' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
            ],
            'custom_fields' => [],
        ]);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(0, $fields);
    }

    public function test_get_enabled_fields_respects_required_flag(): void
    {
        $this->mockResourceWithFieldConfig([
            'predefined_fields' => [
                'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
                'notes' => ['enabled' => true, 'required' => false, 'visibility' => 'public'],
            ],
            'custom_fields' => [],
        ]);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(2, $fields);
        $this->assertTrue($fields[0]->required);
        $this->assertFalse($fields[1]->required);
    }

    public function test_get_enabled_fields_respects_visibility(): void
    {
        $this->mockResourceWithFieldConfig([
            'predefined_fields' => [
                'activity_name' => ['enabled' => true, 'required' => false, 'visibility' => 'public'],
                'contact_phone' => ['enabled' => true, 'required' => false, 'visibility' => 'admin_only'],
            ],
            'custom_fields' => [],
        ]);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(2, $fields);
        $this->assertSame(FieldVisibility::Public->value, $fields[0]->visibility);
        $this->assertSame(FieldVisibility::AdminOnly->value, $fields[1]->visibility);
    }

    public function test_get_enabled_fields_returns_empty_when_no_field_config(): void
    {
        $this->mockResourceWithFieldConfig(null);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(0, $fields);
    }

    public function test_falls_back_to_global_config_when_resource_has_no_field_config(): void
    {
        $this->mockResourceWithFieldConfig(null);

        config()->set('modules.settings.venue-bookings.predefined_fields', [
            'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
        ]);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(1, $fields);
        $this->assertSame('activity_name', $fields[0]->key);
    }

    public function test_falls_back_to_global_config_when_resource_not_found(): void
    {
        $this->resourceRepository->method('find')->willReturn(null);

        config()->set('modules.settings.venue-bookings.predefined_fields', [
            'notes' => ['enabled' => true, 'required' => false, 'visibility' => 'authenticated'],
        ]);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(1, $fields);
        $this->assertSame('notes', $fields[0]->key);
    }

    public function test_get_enabled_fields_includes_custom_fields(): void
    {
        $this->mockResourceWithFieldConfig([
            'predefined_fields' => [
                'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
            ],
            'custom_fields' => [
                ['key' => 'num-socios', 'label' => 'Nº Socios', 'type' => 'number', 'required' => true, 'visibility' => 'admin_only'],
            ],
        ]);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(2, $fields);
        $this->assertSame('activity_name', $fields[0]->key);
        $this->assertSame('num-socios', $fields[1]->key);
    }

    public function test_custom_select_field_options_are_read_one_per_line(): void
    {
        // The admin form stores the options of a select field as text, one per line
        $this->mockResourceWithFieldConfig([
            'predefined_fields' => [],
            'custom_fields' => [
                ['key' => 'game-system', 'label' => 'Sistema de juego', 'type' => 'select', 'required' => true, 'visibility' => 'public', 'options' => "Warhammer\r\n D&D \n\nPathfinder\n"],
                ['key' => 'extra', 'label' => 'Extra', 'type' => 'text', 'required' => false, 'visibility' => 'public', 'options' => null],
                ['key' => 'other', 'label' => 'Otro', 'type' => 'text', 'required' => false, 'visibility' => 'public'],
            ],
        ]);

        $fields = $this->service->getEnabledFields($this->resourceId);

        $this->assertCount(3, $fields);
        // A custom option is its own value
        $this->assertSame([
            ['value' => 'Warhammer', 'label' => 'Warhammer'],
            ['value' => 'D&D', 'label' => 'D&D'],
            ['value' => 'Pathfinder', 'label' => 'Pathfinder'],
        ], $fields[0]->options);
        $this->assertNull($fields[1]->options);
        $this->assertNull($fields[2]->options);
    }

    public function test_game_tables_and_campaigns_are_offered_by_id_with_their_title_as_label(): void
    {
        config()->set('modules.settings.venue-bookings.associations.game_tables_enabled', true);
        config()->set('modules.settings.venue-bookings.associations.campaigns_enabled', true);
        config()->set('modules.settings.venue-bookings.predefined_fields', []);
        config()->set('modules.settings.venue-bookings.custom_fields', []);

        $integration = $this->createStub(ModuleIntegrationServiceInterface::class);
        $integration->method('isGameTablesModuleAvailable')->willReturn(true);
        $integration->method('getAvailableGameTables')->willReturn([['id' => 'table-1', 'label' => 'Kill Team, liga']]);
        $integration->method('getAvailableCampaigns')->willReturn([['id' => 'campaign-1', 'label' => 'La maldición de Strahd']]);
        $this->resourceRepository->method('find')->willReturn(null);
        $service = new BookingFieldConfigService($integration, $this->resourceRepository);

        $fields = $service->getEnabledFields($this->resourceId);

        $this->assertSame(['game_table_id', 'campaign_id'], array_map(fn ($field): string => $field->key, $fields));
        $this->assertSame([['value' => 'table-1', 'label' => 'Kill Team, liga']], $fields[0]->options);
        $this->assertSame([['value' => 'campaign-1', 'label' => 'La maldición de Strahd']], $fields[1]->options);
    }

    public function test_get_default_field_config_reads_from_global_config(): void
    {
        config()->set('modules.settings.venue-bookings.predefined_fields', [
            'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
        ]);
        config()->set('modules.settings.venue-bookings.custom_fields', [
            ['key' => 'extra', 'label' => 'Extra', 'type' => 'text', 'required' => false, 'visibility' => 'public'],
        ]);

        $config = $this->service->getDefaultFieldConfig();

        $this->assertCount(1, $config->predefinedFields);
        $this->assertCount(1, $config->customFields);
    }

    /**
     * @param  array<string, mixed>|null  $fieldConfigData
     */
    private function mockResourceWithFieldConfig(?array $fieldConfigData): void
    {
        $fieldConfig = $fieldConfigData !== null
            ? ResourceFieldConfig::fromArray($fieldConfigData)
            : null;

        $resource = new BookableResource(
            id: BookableResourceId::fromString($this->resourceId),
            name: 'Test Resource',
            slug: 'test-resource',
            description: null,
            capacity: null,
            status: BookableResourceStatus::Active,
            sortOrder: 0,
            fieldConfig: $fieldConfig,
        );

        $this->resourceRepository->method('find')->willReturn($resource);
    }
}
