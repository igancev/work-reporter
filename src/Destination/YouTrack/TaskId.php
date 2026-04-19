<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination\YouTrack;

final readonly class TaskId
{
    public static function fromString(string $taskId): self
    {
        [$projectAlias, $numericId] = explode('-', $taskId);

        return new self($projectAlias, (int)$numericId);
    }

    private function __construct(
        private string $projectAlias,
        private int $numericId,
    ) {
    }

    public function getProjectAlias(): string
    {
        return $this->projectAlias;
    }

    public function getNumericId(): int
    {
        return $this->numericId;
    }

    public function toString(): string
    {
        return "{$this->projectAlias}-{$this->numericId}";
    }
}
