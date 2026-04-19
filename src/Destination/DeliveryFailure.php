<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use Igancev\WorkReporter\TimeEntry;
use Throwable;

final readonly class DeliveryFailure
{
    public function __construct(
        public TimeEntry $timeEntry,
        public Throwable $exception,
    ) {
    }
}
