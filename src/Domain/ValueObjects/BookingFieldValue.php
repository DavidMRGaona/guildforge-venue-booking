<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\ValueObjects;

final readonly class BookingFieldValue
{
    public function __construct(
        public string $fieldKey,
        public string $fieldLabel,
        public mixed $value,
        public string $visibility,
    ) {
    }

    /**
     * @return array{field_key: string, field_label: string, value: mixed, visibility: string}
     */
    public function toArray(): array
    {
        return [
            'field_key' => $this->fieldKey,
            'field_label' => $this->fieldLabel,
            'value' => $this->value,
            'visibility' => $this->visibility,
        ];
    }

    /**
     * @param  array{field_key: string, field_label: string, value: mixed, visibility: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fieldKey: $data['field_key'],
            fieldLabel: $data['field_label'],
            value: $data['value'],
            visibility: $data['visibility'],
        );
    }
}
