<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use Igancev\WorkReporter\TimeEntry;

/**
 * @api
 */
interface Destination
{
    /**
     * @param iterable<TimeEntry> $timeEntries
     * @return DeliveryStream<DeliveryEvent>
     * @throws DestinationException
     */
    public function logTimeEntries(iterable $timeEntries): DeliveryStream;
}
