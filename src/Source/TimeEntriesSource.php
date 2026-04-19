<?php

namespace Igancev\WorkReporter\Source;

use DateTimeImmutable;
use Igancev\WorkReporter\TimeEntry;

/**
 * @api
 */
interface TimeEntriesSource
{
    /**
     * @return TimeEntry[]
     * @throws SourceException
     */
    public function fetchTimeEntries(DateTimeImmutable $from, DateTimeImmutable $to): iterable;
}
