<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use Igancev\WorkReporter\TimeEntry;
use Throwable;

final readonly class DeliveryEvent
{
    public function __construct(
        public TimeEntry $timeEntry,
        public float $durationMs,
        public bool $success,
        public ?Throwable $error = null,
    ) {
    }
}
