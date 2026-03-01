<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\ValueObjects;

final readonly class ResourceFieldConfig
{
    /**
     * @param  array<string, array{enabled: bool, required: bool, visibility: string}>  $predefinedFields
     * @param  array<int, array{key: string, label: string, type: string, required: bool, visibility: string, options?: string|null}>  $customFields
     */
    public function __construct(
        public array $predefinedFields,
        public array $customFields,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            predefinedFields: $data['predefined_fields'] ?? [],
            customFields: $data['custom_fields'] ?? [],
        );
    }

    /**
     * @param  array<string, array{enabled: bool, required: bool, visibility: string}>  $predefinedFields
     * @param  array<int, array{key: string, label: string, type: string, required: bool, visibility: string, options?: string|null}>  $customFields
     */
    public static function fromGlobalConfig(array $predefinedFields, array $customFields): self
    {
        return new self(
            predefinedFields: $predefinedFields,
            customFields: $customFields,
        );
    }

    /**
     * @return array{predefined_fields: array<string, array{enabled: bool, required: bool, visibility: string}>, custom_fields: array<int, array{key: string, label: string, type: string, required: bool, visibility: string, options?: string|null}>}
     */
    public function toArray(): array
    {
        return [
            'predefined_fields' => $this->predefinedFields,
            'custom_fields' => $this->customFields,
        ];
    }

    /**
     * @return array<string, array{enabled: bool, required: bool, visibility: string}>
     */
    public function getEnabledPredefinedFields(): array
    {
        return array_filter(
            $this->predefinedFields,
            fn (array $config): bool => (bool) ($config['enabled'] ?? false),
        );
    }
}
