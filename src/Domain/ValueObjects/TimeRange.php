<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class TimeRange
{
    public function __construct(
        public string $startTime,
        public string $endTime,
    ) {
        if ($startTime === $endTime) {
            throw new InvalidArgumentException("Start time and end time cannot be equal ({$startTime}).");
        }
    }

    public function crossesMidnight(): bool
    {
        return $this->endTime < $this->startTime;
    }

    public function overlaps(self $other): bool
    {
        $thisSegments = $this->toMinuteSegments();
        $otherSegments = $other->toMinuteSegments();

        foreach ($thisSegments as [$aStart, $aEnd]) {
            foreach ($otherSegments as [$bStart, $bEnd]) {
                if ($aStart < $bEnd && $aEnd > $bStart) {
                    return true;
                }
            }
        }

        return false;
    }

    public function durationMinutes(): int
    {
        $start = strtotime($this->startTime);
        $end = strtotime($this->endTime);

        if ($this->crossesMidnight()) {
            $end += 86400;
        }

        return (int) (($end - $start) / 60);
    }

    public function contains(string $time): bool
    {
        if ($this->crossesMidnight()) {
            return $time >= $this->startTime || $time < $this->endTime;
        }

        return $time >= $this->startTime && $time < $this->endTime;
    }

    /**
     * @return array<array{int, int}>
     */
    private function toMinuteSegments(): array
    {
        $start = $this->timeToMinutes($this->startTime);
        $end = $this->timeToMinutes($this->endTime);

        if ($this->crossesMidnight()) {
            return [[$start, 1440], [0, $end]];
        }

        return [[$start, $end]];
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);

        return (int) $hours * 60 + (int) $minutes;
    }

    /**
     * @return array{start_time: string, end_time: string}
     */
    public function toArray(): array
    {
        return [
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
        ];
    }

    /**
     * @param  array{start_time: string, end_time: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            startTime: $data['start_time'],
            endTime: $data['end_time'],
        );
    }
}
