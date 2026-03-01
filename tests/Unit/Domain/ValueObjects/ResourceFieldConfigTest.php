<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Domain\ValueObjects;

use Modules\VenueBookings\Domain\ValueObjects\ResourceFieldConfig;
use Tests\TestCase;

final class ResourceFieldConfigTest extends TestCase
{
    public function test_it_creates_from_array_with_predefined_and_custom_fields(): void
    {
        $data = [
            'predefined_fields' => [
                'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
                'num_participants' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
                'contact_phone' => ['enabled' => true, 'required' => false, 'visibility' => 'admin_only'],
                'notes' => ['enabled' => true, 'required' => true, 'visibility' => 'authenticated'],
            ],
            'custom_fields' => [
                ['key' => 'num-socios', 'label' => 'Nº Socios', 'type' => 'number', 'required' => true, 'visibility' => 'admin_only'],
            ],
        ];

        $config = ResourceFieldConfig::fromArray($data);

        $this->assertCount(4, $config->predefinedFields);
        $this->assertCount(1, $config->customFields);
    }

    public function test_it_serializes_to_array_and_back(): void
    {
        $data = [
            'predefined_fields' => [
                'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
                'notes' => ['enabled' => false, 'required' => false, 'visibility' => 'authenticated'],
            ],
            'custom_fields' => [
                ['key' => 'game-system', 'label' => 'Sistema de juego', 'type' => 'select', 'required' => true, 'visibility' => 'public', 'options' => "Warhammer\nD&D"],
            ],
        ];

        $config = ResourceFieldConfig::fromArray($data);
        $array = $config->toArray();

        $this->assertArrayHasKey('predefined_fields', $array);
        $this->assertArrayHasKey('custom_fields', $array);
        $this->assertSame(true, $array['predefined_fields']['activity_name']['enabled']);
        $this->assertSame(false, $array['predefined_fields']['notes']['enabled']);
        $this->assertSame('game-system', $array['custom_fields'][0]['key']);
    }

    public function test_get_enabled_predefined_fields_returns_only_enabled(): void
    {
        $data = [
            'predefined_fields' => [
                'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
                'num_participants' => ['enabled' => false, 'required' => false, 'visibility' => 'public'],
                'contact_phone' => ['enabled' => true, 'required' => false, 'visibility' => 'admin_only'],
                'notes' => ['enabled' => false, 'required' => false, 'visibility' => 'authenticated'],
            ],
            'custom_fields' => [],
        ];

        $config = ResourceFieldConfig::fromArray($data);
        $enabled = $config->getEnabledPredefinedFields();

        $this->assertCount(2, $enabled);
        $this->assertArrayHasKey('activity_name', $enabled);
        $this->assertArrayHasKey('contact_phone', $enabled);
    }

    public function test_get_custom_fields_returns_all_custom_fields(): void
    {
        $data = [
            'predefined_fields' => [],
            'custom_fields' => [
                ['key' => 'field-a', 'label' => 'Field A', 'type' => 'text', 'required' => false, 'visibility' => 'public'],
                ['key' => 'field-b', 'label' => 'Field B', 'type' => 'number', 'required' => true, 'visibility' => 'authenticated'],
            ],
        ];

        $config = ResourceFieldConfig::fromArray($data);

        $this->assertCount(2, $config->customFields);
        $this->assertSame('field-a', $config->customFields[0]['key']);
        $this->assertSame('field-b', $config->customFields[1]['key']);
    }

    public function test_from_global_config_builds_from_settings_arrays(): void
    {
        $predefinedFields = [
            'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
            'num_participants' => ['enabled' => true, 'required' => false, 'visibility' => 'authenticated'],
        ];
        $customFields = [
            ['key' => 'num-socios', 'label' => 'Nº Socios', 'type' => 'number', 'required' => true, 'visibility' => 'admin_only'],
        ];

        $config = ResourceFieldConfig::fromGlobalConfig($predefinedFields, $customFields);

        $this->assertCount(2, $config->predefinedFields);
        $this->assertCount(1, $config->customFields);
        $this->assertTrue($config->predefinedFields['activity_name']['enabled']);
    }

    public function test_empty_config_returns_empty_arrays(): void
    {
        $config = ResourceFieldConfig::fromArray([
            'predefined_fields' => [],
            'custom_fields' => [],
        ]);

        $this->assertEmpty($config->predefinedFields);
        $this->assertEmpty($config->customFields);
        $this->assertEmpty($config->getEnabledPredefinedFields());
    }

    public function test_from_array_handles_missing_keys_gracefully(): void
    {
        $config = ResourceFieldConfig::fromArray([]);

        $this->assertEmpty($config->predefinedFields);
        $this->assertEmpty($config->customFields);
    }
}
