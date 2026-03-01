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
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class BookingFieldConfigServiceTest extends TestCase
{
    private ModuleIntegrationServiceInterface&MockObject $moduleIntegration;

    private BookableResourceRepositoryInterface&MockObject $resourceRepository;

    private BookingFieldConfigService $service;

    private string $resourceId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleIntegration = $this->createMock(ModuleIntegrationServiceInterface::class);
        $this->moduleIntegration->method('isGameTablesModuleAvailable')->willReturn(false);
        $this->moduleIntegration->method('isTournamentsModuleAvailable')->willReturn(false);

        $this->resourceRepository = $this->createMock(BookableResourceRepositoryInterface::class);

        $this->service = new BookingFieldConfigService($this->moduleIntegration, $this->resourceRepository);

        $this->resourceId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    }

    protected function tearDown(): void
    {
        config()->offsetUnset('modules.settings.venue-bookings');
        config()->offsetUnset('venue-bookings');
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

    public function test_get_default_field_config_reads_from_global_config(): void
    {
        config()->set('modules.settings.venue-bookings.predefined_fields', [
            'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
        ]);
        config()->set('modules.settings.venue-bookings.custom_fields', [
            ['key' => 'extra', 'label' => 'Extra', 'type' => 'text', 'required' => false, 'visibility' => 'public'],
        ]);

        $config = $this->service->getDefaultFieldConfig();

        $this->assertInstanceOf(ResourceFieldConfig::class, $config);
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
