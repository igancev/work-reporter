<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination\YouTrack;

final readonly class TaskIdCollection
{
    /**
     * @param TaskId[] $taskIds
     */
    public function __construct(private array $taskIds)
    {
    }

    public function filterUniqueByProject(): self
    {
        $unique = [];
        foreach ($this->taskIds as $taskId) {
            $alias = $taskId->getProjectAlias();
            if (!isset($unique[$alias])) {
                $unique[$alias] = $taskId;
            }
        }

        return new self(array_values($unique));
    }

    /**
     * @return TaskId[]
     */
    public function toArray(): array
    {
        return $this->taskIds;
    }
}
