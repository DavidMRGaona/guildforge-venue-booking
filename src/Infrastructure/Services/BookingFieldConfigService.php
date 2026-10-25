<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Validation\Rule;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Domain\Enums\BookingFieldType;
use Modules\VenueBookings\Domain\Enums\FieldVisibility;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldConfig;
use Modules\VenueBookings\Domain\ValueObjects\ResourceFieldConfig;

final readonly class BookingFieldConfigService implements BookingFieldConfigServiceInterface
{
    public function __construct(
        private ModuleIntegrationServiceInterface $moduleIntegration,
        private BookableResourceRepositoryInterface $resourceRepository,
        private BookingSettingsReader $settings = new BookingSettingsReader,
    ) {}

    /**
     * @return array<BookingFieldConfig>
     */
    public function getEnabledFields(string $resourceId, ?UserModel $user = null): array
    {
        $config = $this->resolveFieldConfig($resourceId);

        $fields = $this->getPredefinedFieldsForConfig($config);
        $fields = array_merge($fields, $this->getCustomFieldsForConfig($config));
        $fields = array_merge($fields, $this->getAssociationFields($user));

        return $fields;
    }

    /**
     * @return array<BookingFieldConfig>
     */
    public function getVisibleFields(string $resourceId, ?UserModel $user): array
    {
        $fields = $this->getEnabledFields($resourceId, $user);

        return array_values(array_filter(
            $fields,
            fn (BookingFieldConfig $field): bool => $this->isVisibleToUser($field, $user),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidationRules(string $resourceId, ?UserModel $user): array
    {
        $rules = [];
        $visibleFields = $this->getVisibleFields($resourceId, $user);

        foreach ($visibleFields as $field) {
            // Rule objects rather than a pipe string: an option may contain a comma or a pipe
            $rules["field_values.{$field->key}"] = [
                $field->required ? 'required' : 'nullable',
                ...match (BookingFieldType::from($field->type)) {
                    BookingFieldType::Text => ['string', 'max:255'],
                    BookingFieldType::Textarea => ['string', 'max:2000'],
                    BookingFieldType::Number => ['integer', 'min:0'],
                    BookingFieldType::Select => ['string', Rule::in($field->optionValues())],
                    BookingFieldType::Toggle => ['boolean'],
                },
            ];
        }

        return $rules;
    }

    /**
     * @return array<BookingFieldConfig>
     */
    public function getAssociationFields(?UserModel $user = null): array
    {
        $fields = [];

        if ($this->moduleIntegration->isGameTablesModuleAvailable()) {
            $enabled = (bool) $this->settings->get('associations.game_tables_enabled');

            if ($enabled) {
                $visibility = FieldVisibility::from(
                    (string) $this->settings->get('associations.game_tables_visibility'),
                );
                $required = (bool) $this->settings->get('associations.game_tables_required');

                $gameTables = $user !== null
                    ? $this->moduleIntegration->getUserGameTables((string) $user->id)
                    : $this->moduleIntegration->getAvailableGameTables();

                $fields[] = new BookingFieldConfig(
                    key: BookingFieldConfig::GAME_TABLE_KEY,
                    label: __('venue-bookings::messages.fields.game_table'),
                    type: BookingFieldType::Select->value,
                    required: $required,
                    visibility: $visibility->value,
                    options: $this->associationOptions($gameTables),
                );
            }
        }

        if ($this->moduleIntegration->isGameTablesModuleAvailable()) {
            $enabled = (bool) $this->settings->get('associations.campaigns_enabled');

            if ($enabled) {
                $visibility = FieldVisibility::from(
                    (string) $this->settings->get('associations.campaigns_visibility'),
                );
                $required = (bool) $this->settings->get('associations.campaigns_required');

                $campaigns = $user !== null
                    ? $this->moduleIntegration->getUserCampaigns((string) $user->id)
                    : $this->moduleIntegration->getAvailableCampaigns();

                $fields[] = new BookingFieldConfig(
                    key: BookingFieldConfig::CAMPAIGN_KEY,
                    label: __('venue-bookings::messages.fields.campaign'),
                    type: BookingFieldType::Select->value,
                    required: $required,
                    visibility: $visibility->value,
                    options: $this->associationOptions($campaigns),
                );
            }
        }

        if ($this->moduleIntegration->isTournamentsModuleAvailable()) {
            $enabled = (bool) $this->settings->get('associations.tournaments_enabled');

            if ($enabled) {
                $visibility = FieldVisibility::from(
                    (string) $this->settings->get('associations.tournaments_visibility'),
                );
                $required = (bool) $this->settings->get('associations.tournaments_required');

                $fields[] = new BookingFieldConfig(
                    key: BookingFieldConfig::TOURNAMENT_KEY,
                    label: __('venue-bookings::messages.fields.tournament'),
                    type: BookingFieldType::Select->value,
                    required: $required,
                    visibility: $visibility->value,
                    options: $this->associationOptions($this->moduleIntegration->getAvailableTournaments()),
                );
            }
        }

        return $fields;
    }

    public function getDefaultFieldConfig(): ResourceFieldConfig
    {
        $predefinedFields = (array) $this->settings->get('predefined_fields');
        $customFields = (array) $this->settings->get('custom_fields');

        return ResourceFieldConfig::fromGlobalConfig($predefinedFields, $customFields);
    }

    private function resolveFieldConfig(string $resourceId): ResourceFieldConfig
    {
        $resource = $this->resourceRepository->find(BookableResourceId::fromString($resourceId));

        if ($resource !== null && $resource->fieldConfig !== null) {
            return $resource->fieldConfig;
        }

        return $this->getDefaultFieldConfig();
    }

    /**
     * @return array<BookingFieldConfig>
     */
    private function getPredefinedFieldsForConfig(ResourceFieldConfig $config): array
    {
        $fields = [];
        $predefinedMeta = [
            'activity_name' => [
                'label' => __('venue-bookings::messages.fields.activity_name'),
                'type' => BookingFieldType::Text,
            ],
            'num_participants' => [
                'label' => __('venue-bookings::messages.fields.num_participants'),
                'type' => BookingFieldType::Number,
            ],
            'contact_phone' => [
                'label' => __('venue-bookings::messages.fields.contact_phone'),
                'type' => BookingFieldType::Text,
            ],
            'notes' => [
                'label' => __('venue-bookings::messages.fields.notes'),
                'type' => BookingFieldType::Textarea,
            ],
        ];

        foreach ($config->getEnabledPredefinedFields() as $key => $fieldConfig) {
            if (! isset($predefinedMeta[$key])) {
                continue;
            }

            $meta = $predefinedMeta[$key];
            $required = (bool) ($fieldConfig['required'] ?? false);
            $visibility = FieldVisibility::from($fieldConfig['visibility'] ?? 'authenticated');

            $fields[] = new BookingFieldConfig(
                key: $key,
                label: $meta['label'],
                type: $meta['type']->value,
                required: $required,
                visibility: $visibility->value,
            );
        }

        return $fields;
    }

    /**
     * @return array<BookingFieldConfig>
     */
    private function getCustomFieldsForConfig(ResourceFieldConfig $config): array
    {
        return array_map(
            fn (array $field): BookingFieldConfig => BookingFieldConfig::fromArray([
                ...$field,
                'options' => $this->parseOptions($field['options'] ?? null),
            ]),
            $config->customFields,
        );
    }

    /**
     * The admin form stores the options of a select field as text, one per line; each option is
     * its own value.
     *
     * @return list<array{value: string, label: string}>|null
     */
    private function parseOptions(?string $options): ?array
    {
        if ($options === null) {
            return null;
        }

        $lines = array_filter(
            array_map(trim(...), preg_split('/\R/', $options) ?: []),
            fn (string $option): bool => $option !== '',
        );

        return array_values(array_map(
            fn (string $option): array => ['value' => $option, 'label' => $option],
            $lines,
        ));
    }

    /**
     * A game table, campaign or tournament is sent by its id and shown by its title.
     *
     * @param  array<array{id: string, label: string}>  $items
     * @return list<array{value: string, label: string}>
     */
    private function associationOptions(array $items): array
    {
        return array_values(array_map(
            fn (array $item): array => ['value' => $item['id'], 'label' => $item['label']],
            $items,
        ));
    }

    private function isVisibleToUser(BookingFieldConfig $field, ?UserModel $user): bool
    {
        return match (FieldVisibility::from($field->visibility)) {
            FieldVisibility::Public => true,
            FieldVisibility::Authenticated => $user !== null,
            FieldVisibility::Permission => $user !== null && $user->isAdmin(),
            FieldVisibility::AdminOnly => $user !== null && $user->isAdmin(),
        };
    }
}
