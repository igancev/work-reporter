<?php

namespace Igancev\WorkReporter;

use DateTimeImmutable;
use InvalidArgumentException;

readonly class TimeEntry
{
    public function __construct(
        public string $taskId,
        public Duration $duration,
        public string $workType,
        public DateTimeImmutable $date,
        public string $comment = '',
    ) {
        if (trim($this->workType) === '') {
            throw new InvalidArgumentException('Work type cannot be empty');
        }
    }
}
