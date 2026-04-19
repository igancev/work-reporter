<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination\YouTrack;

use DateTimeImmutable;
use Igancev\WorkReporter\Duration;

final readonly class WorkItem
{
    public function __construct(
        public TaskId $taskId,
        public DateTimeImmutable $date,
        public Duration $duration,
        public WorkItemType $workItemType,
        public string $comment,
    ) {
    }
}
