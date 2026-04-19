<?php

declare(strict_types=1);

namespace Igancev\WorkReporter;

use InvalidArgumentException;

final readonly class Duration
{
    private string $duration;

    private function __construct(
        private int $milliseconds,
    ) {
        if ($this->milliseconds < 0) {
            throw new InvalidArgumentException('Milliseconds must be positive');
        }

        $this->duration = $this->calculateDuration();
    }

    public static function fromMilliseconds(int $ms): self
    {
        return new self($ms);
    }

    public function toString(): string
    {
        return $this->duration;
    }

    public function toMilliseconds(): int
    {
        return $this->milliseconds;
    }

    public function toMinutes(): int
    {
        return (int)round($this->milliseconds / 60000);
    }

    public function equals(self $other): bool
    {
        return $this->milliseconds === $other->milliseconds;
    }

    public function isLessThan(self $other): bool
    {
        return $this->milliseconds < $other->milliseconds;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->milliseconds > $other->milliseconds;
    }

    public function add(self $other): self
    {
        return new self($this->milliseconds + $other->milliseconds);
    }

    public static function fromMinutes(int $minutes): self
    {
        return new self($minutes * 60000);
    }

    public static function fromString(string $duration): self
    {
        $minutes = 0;
        if (preg_match('/(\d+)h/i', $duration, $matches)) {
            $minutes += (int)$matches[1] * 60;
        }
        if (preg_match('/(\d+)m/i', $duration, $matches)) {
            $minutes += (int)$matches[1];
        }

        if ($minutes === 0 && is_numeric($duration)) {
            $minutes = (int)$duration;
        }

        return self::fromMilliseconds($minutes * 60000);
    }

    private function calculateDuration(): string
    {
        $totalMinutes = $this->toMinutes();
        $hours = (int)($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }

        if ($minutes > 0 || $hours === 0) {
            $parts[] = "{$minutes}m";
        }

        return implode(' ', $parts);
    }
}
