<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\ValueObjects;

final readonly class BookingFieldConfig
{
    /** Fields that link the booking to a game table, campaign or tournament, stored in their own columns */
    public const string GAME_TABLE_KEY = 'game_table_id';

    public const string CAMPAIGN_KEY = 'campaign_id';

    public const string TOURNAMENT_KEY = 'tournament_id';

    /** The number of participants, capped by the capacity of the space when it has one */
    public const string NUM_PARTICIPANTS_KEY = 'num_participants';

    /**
     * Each option is sent as `value` and shown as `label`: a game table, campaign or tournament
     * is chosen by its id, a custom option is its own value.
     *
     * @param  list<array{value: string, label: string}>|null  $options
     * @param  int|null  $max  the largest number a number field takes, when it has a limit
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required,
        public string $visibility,
        public ?array $options = null,
        public ?int $max = null,
    ) {}

    public function isSelectField(): bool
    {
        return $this->type === 'select';
    }

    public function isAssociation(): bool
    {
        return in_array($this->key, [self::GAME_TABLE_KEY, self::CAMPAIGN_KEY, self::TOURNAMENT_KEY], true);
    }

    /**
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_column($this->options ?? [], 'value');
    }

    /**
     * @return array{key: string, label: string, type: string, required: bool, visibility: string, options: list<array{value: string, label: string}>|null, max: int|null}
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
            'max' => $this->max,
        ];
    }

    /**
     * @param  array{key: string, label: string, type: string, required: bool, visibility: string, options?: list<array{value: string, label: string}>|null}  $data
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
