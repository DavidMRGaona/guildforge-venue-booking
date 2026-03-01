<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\ValueObjects;

final readonly class BookingFieldConfig
{
    /**
     * @param  array<string>|null  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required,
        public string $visibility,
        public ?array $options = null,
    ) {
    }

    public function isSelectField(): bool
    {
        return $this->type === 'select';
    }

    /**
     * @return array{key: string, label: string, type: string, required: bool, visibility: string, options: array<string>|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'visibility' => $this->visibility,
            'options' => $this->options,
        ];
    }

    /**
     * @param  array{key: string, label: string, type: string, required: bool, visibility: string, options?: array<string>|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: $data['key'],
            label: $data['label'],
            type: $data['type'],
            required: $data['required'],
            visibility: $data['visibility'],
            options: $data['options'] ?? null,
        );
    }
}
