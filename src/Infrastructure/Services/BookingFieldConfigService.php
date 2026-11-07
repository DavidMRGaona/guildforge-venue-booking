<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Validation\Rule;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Domain\Entities\BookableResource;
use Modules\VenueBookings\Domain\Enums\BookingFieldType;
use Modules\VenueBookings\Domain\Enums\FieldVisibility;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldConfig;
use Modules\VenueBookings\Domain\ValueObjects\ResourceFieldConfig;

final readonly class BookingFieldConfigService implements BookingFieldConfigServiceInterface
{
    private const string MANAGE_PERMISSION = 'venuebookings:bookings.manage';

    public function __construct(
        private ModuleIntegrationServiceInterface $moduleIntegration,
        private BookableResourceRepositoryInterface $resourceRepository,
        private AuthorizationServiceInterface $authorization,
        private BookingSettingsReader $settings = new BookingSettingsReader,
    ) {}

    /**
     * @return array<BookingFieldConfig>
     */
    public function getEnabledFields(string $resourceId, ?UserModel $user = null): array
    {
        $resource = $this->findResource($resourceId);
        $config = $resource->fieldConfig ?? $this->getDefaultFieldConfig();

        $fields = $this->getPredefinedFieldsForConfig($config, $resource?->capacity);
        $fields = array_merge($fields, $this->getCustomFieldsForConfig($config));

        return array_merge($fields, $this->getAssociationFields($user));
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
                    BookingFieldType::Number => ['integer', 'min:0', ...($field->max !== null ? ['max:'.$field->max] : [])],
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

                $gameTables = $user instanceof UserModel
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

                $campaigns = $user instanceof UserModel
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

    private function findResource(string $resourceId): ?BookableResource
    {
        return $this->resourceRepository->find(BookableResourceId::fromString($resourceId));
    }

    /**
     * @return array<BookingFieldConfig>
     */
    private function getPredefinedFieldsForConfig(ResourceFieldConfig $config, ?int $capacity): array
    {
        $fields = [];
        $predefinedMeta = [
            'activity_name' => [
                'label' => __('venue-bookings::messages.fields.activity_name'),
                'type' => BookingFieldType::Text,
            ],
            BookingFieldConfig::NUM_PARTICIPANTS_KEY => [
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
                // The space's capacity is the most people one booking may bring
                max: $key === BookingFieldConfig::NUM_PARTICIPANTS_KEY ? $capacity : null,
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
            FieldVisibility::Authenticated => $user instanceof UserModel,
            FieldVisibility::Permission => $user instanceof UserModel && $this->authorization->can($user, self::MANAGE_PERMISSION),
            FieldVisibility::AdminOnly => $user instanceof UserModel && $user->isAdmin(),
        };
    }
}
