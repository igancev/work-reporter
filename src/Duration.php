<?php

declare(strict_types=1);

namespace Igancev\WorkReporter;

use InvalidArgumentException;

/**
 * Represents a time duration.
 *
 * Supported string formats:
 * - "1h 30m" (hours and minutes)
 * - "2h" (hours only)
 * - "45m" (minutes only)
 * - "90" (numeric string, interpreted as minutes)
 *
 * Use static factories for creation: fromMilliseconds(), fromMinutes(), or fromString().
 */
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

    public static function fromMinutes(int $minutes): self
    {
        return new self($minutes * 60000);
    }

    /**
     * @throws InvalidDurationException
     */
    public static function fromString(string $duration): self
    {
        $input = trim($duration);
        if ($input === '') {
            throw InvalidDurationException::forInput($duration, 'String cannot be empty');
        }

        if (ctype_digit($input)) {
            $minutes = (int)$input;
            if ($minutes > (PHP_INT_MAX / 60000)) {
                throw InvalidDurationException::forInput($duration, 'Value is too large');
            }
            return self::fromMinutes($minutes);
        }

        if (!preg_match('/^(?:\d+[hm]\s*)+$/i', $input)) {
            throw InvalidDurationException::forInput(
                $duration,
                'Expected format: "1h 30m", "2h", "45m", or a number',
            );
        }

        preg_match_all('/(\d+)([hm])/i', $input, $matches, PREG_SET_ORDER);

        $minutes = 0;
        foreach ($matches as $match) {
            $minutes += strtolower($match[2]) === 'h'
                ? (int)$match[1] * 60
                : (int)$match[1];
        }

        if ($minutes > (PHP_INT_MAX / 60000)) {
            throw InvalidDurationException::forInput($duration, 'Value is too large');
        }

        return self::fromMinutes($minutes);
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
