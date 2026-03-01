<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
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
            $fieldRules = [];

            if ($field->required) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            $fieldRules[] = match (BookingFieldType::from($field->type)) {
                BookingFieldType::Text => 'string|max:255',
                BookingFieldType::Textarea => 'string|max:2000',
                BookingFieldType::Number => 'integer|min:0',
                BookingFieldType::Select => 'string|in:'.implode(',', $field->options ?? []),
                BookingFieldType::Toggle => 'boolean',
            };

            $rules["field_values.{$field->key}"] = implode('|', $fieldRules);
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
            $enabled = (bool) (config('modules.settings.venue-bookings.associations.game_tables_enabled')
                ?? config('venue-bookings.associations.game_tables_enabled', false));

            if ($enabled) {
                $visibility = FieldVisibility::from(
                    (string) (config('modules.settings.venue-bookings.associations.game_tables_visibility')
                        ?? config('venue-bookings.associations.game_tables_visibility', 'authenticated')),
                );
                $required = (bool) (config('modules.settings.venue-bookings.associations.game_tables_required')
                    ?? config('venue-bookings.associations.game_tables_required', false));

                $gameTables = $user !== null
                    ? $this->moduleIntegration->getUserGameTables((string) $user->id)
                    : $this->moduleIntegration->getAvailableGameTables();

                $fields[] = new BookingFieldConfig(
                    key: 'game_table_id',
                    label: __('venue-bookings::messages.fields.game_table'),
                    type: BookingFieldType::Select->value,
                    required: $required,
                    visibility: $visibility->value,
                    options: array_map(
                        fn (array $t): string => $t['label'],
                        $gameTables,
                    ),
                );
            }
        }

        if ($this->moduleIntegration->isGameTablesModuleAvailable()) {
            $enabled = (bool) (config('modules.settings.venue-bookings.associations.campaigns_enabled')
                ?? config('venue-bookings.associations.campaigns_enabled', false));

            if ($enabled) {
                $visibility = FieldVisibility::from(
                    (string) (config('modules.settings.venue-bookings.associations.campaigns_visibility')
                        ?? config('venue-bookings.associations.campaigns_visibility', 'authenticated')),
                );
                $required = (bool) (config('modules.settings.venue-bookings.associations.campaigns_required')
                    ?? config('venue-bookings.associations.campaigns_required', false));

                $campaigns = $user !== null
                    ? $this->moduleIntegration->getUserCampaigns((string) $user->id)
                    : $this->moduleIntegration->getAvailableCampaigns();

                $fields[] = new BookingFieldConfig(
                    key: 'campaign_id',
                    label: __('venue-bookings::messages.fields.campaign'),
                    type: BookingFieldType::Select->value,
                    required: $required,
                    visibility: $visibility->value,
                    options: array_map(
                        fn (array $c): string => $c['label'],
                        $campaigns,
                    ),
                );
            }
        }

        if ($this->moduleIntegration->isTournamentsModuleAvailable()) {
            $enabled = (bool) (config('modules.settings.venue-bookings.associations.tournaments_enabled')
                ?? config('venue-bookings.associations.tournaments_enabled', false));

            if ($enabled) {
                $visibility = FieldVisibility::from(
                    (string) (config('modules.settings.venue-bookings.associations.tournaments_visibility')
                        ?? config('venue-bookings.associations.tournaments_visibility', 'authenticated')),
                );
                $required = (bool) (config('modules.settings.venue-bookings.associations.tournaments_required')
                    ?? config('venue-bookings.associations.tournaments_required', false));

                $fields[] = new BookingFieldConfig(
                    key: 'tournament_id',
                    label: __('venue-bookings::messages.fields.tournament'),
                    type: BookingFieldType::Select->value,
                    required: $required,
                    visibility: $visibility->value,
                    options: array_map(
                        fn (array $t): string => $t['label'],
                        $this->moduleIntegration->getAvailableTournaments(),
                    ),
                );
            }
        }

        return $fields;
    }

    public function getDefaultFieldConfig(): ResourceFieldConfig
    {
        $predefinedFields = (array) (config('modules.settings.venue-bookings.predefined_fields')
            ?? config('venue-bookings.predefined_fields', []));
        $customFields = (array) (config('modules.settings.venue-bookings.custom_fields')
            ?? config('venue-bookings.custom_fields', []));

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
            fn (array $field): BookingFieldConfig => BookingFieldConfig::fromArray($field),
            $config->customFields,
        );
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
