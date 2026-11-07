<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament\Resources;

use Livewire\Livewire;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\CreateBookableResource;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

final class BookableResourceCreateTest extends ModuleTestCase
{
    use CreatesUsersWithPermissions;
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookableResourceResource::class]);
        $this->actingAsPanelAdmin();

        config()->set('modules.settings.venue-bookings.predefined_fields', [
            'activity_name' => ['enabled' => true, 'required' => true, 'visibility' => 'public'],
            'num_participants' => ['enabled' => true, 'required' => false, 'visibility' => 'authenticated'],
            'contact_phone' => ['enabled' => false],
            'notes' => ['enabled' => false],
        ]);
        config()->set('modules.settings.venue-bookings.custom_fields', [
            ['key' => 'projector', 'label' => 'Proyector', 'type' => 'text', 'required' => false, 'visibility' => 'public'],
        ]);
    }

    public function test_the_create_form_starts_from_the_default_field_template(): void
    {
        Livewire::test(CreateBookableResource::class)
            ->assertFormSet([
                'field_config.predefined_fields.activity_name.enabled' => true,
                'field_config.predefined_fields.activity_name.required' => true,
                'field_config.predefined_fields.num_participants.enabled' => true,
                'field_config.predefined_fields.contact_phone.enabled' => false,
            ]);
    }

    public function test_a_new_space_keeps_the_template_fields(): void
    {
        Livewire::test(CreateBookableResource::class)
            ->fillForm(['name' => 'Sala nueva'])
            ->call('create')
            ->assertHasNoFormErrors();

        $config = $this->storedFieldConfig('Sala nueva');

        $this->assertTrue($config['predefined_fields']['activity_name']['enabled']);
        $this->assertTrue($config['predefined_fields']['activity_name']['required']);
        $this->assertSame('projector', $config['custom_fields'][0]['key']);
    }

    public function test_changes_made_in_the_form_win_over_the_template(): void
    {
        Livewire::test(CreateBookableResource::class)
            ->fillForm([
                'name' => 'Sala sin campos',
                'field_config.predefined_fields.activity_name.enabled' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $config = $this->storedFieldConfig('Sala sin campos');

        $this->assertFalse($config['predefined_fields']['activity_name']['enabled']);
        $this->assertTrue($config['predefined_fields']['num_participants']['enabled']);
    }

    /**
     * @return array{predefined_fields: array<string, array<string, mixed>>, custom_fields: array<int, array<string, mixed>>}
     */
    private function storedFieldConfig(string $name): array
    {
        $config = BookableResourceModel::query()->where('name', $name)->firstOrFail()->field_config;

        $this->assertIsArray($config);

        return [
            'predefined_fields' => (array) ($config['predefined_fields'] ?? []),
            'custom_fields' => array_values((array) ($config['custom_fields'] ?? [])),
        ];
    }
}
