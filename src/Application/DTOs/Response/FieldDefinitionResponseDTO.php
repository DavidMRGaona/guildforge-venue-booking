<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\DTOs\Response;

use Modules\VenueBookings\Domain\ValueObjects\BookingFieldConfig;

final readonly class FieldDefinitionResponseDTO
{
    /**
     * @param  array<string>|null  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required,
        public ?array $options,
    ) {}

    public static function fromConfig(BookingFieldConfig $config): self
    {
        return new self(
            key: $config->key,
            label: $config->label,
            type: $config->type,
            required: $config->required,
            options: $config->options,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'options' => $this->options,
        ];
    }
}
