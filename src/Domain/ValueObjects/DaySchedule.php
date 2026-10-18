<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class DaySchedule
{
    public string $openTime;

    public string $closeTime;

    public function __construct(
        public int $dayOfWeek,
        string $openTime,
        string $closeTime,
        public bool $isEnabled,
        public ?string $label = null,
    ) {
        $this->openTime = self::normaliseTime($openTime);
        $this->closeTime = self::normaliseTime($closeTime);

        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new InvalidArgumentException("Day of week must be between 0 (Sunday) and 6 (Saturday), got {$dayOfWeek}.");
        }

        if ($isEnabled && $this->openTime === $this->closeTime) {
            throw new InvalidArgumentException("Open time and close time cannot be equal ({$this->openTime}) when enabled.");
        }
    }

    public function crossesMidnight(): bool
    {
        return $this->isEnabled && $this->closeTime < $this->openTime;
    }

    /**
     * @return array{day_of_week: int, open_time: string, close_time: string, is_enabled: bool, label: ?string}
     */
    public function toArray(): array
    {
        return [
            'day_of_week' => $this->dayOfWeek,
            'open_time' => $this->openTime,
            'close_time' => $this->closeTime,
            'is_enabled' => $this->isEnabled,
            'label' => $this->label,
        ];
    }

    /**
     * @param  array{day_of_week: int, open_time: string, close_time: string, is_enabled: bool, label?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dayOfWeek: (int) $data['day_of_week'],
            openTime: $data['open_time'],
            closeTime: $data['close_time'],
            isEnabled: (bool) $data['is_enabled'],
            label: $data['label'] ?? null,
        );
    }

    /**
     * "HH:MM" of "H:MM", "HH:MM" or "HH:MM:SS"; anything else is kept as it is.
     */
    private static function normaliseTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $parts) !== 1) {
            return $time;
        }

        return sprintf('%02d:%s', (int) $parts[1], $parts[2]);
    }
}
