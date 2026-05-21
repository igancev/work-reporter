<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

interface DestinationFactory
{
    /**
     * @throws DestinationException
     */
    public function build(DestinationType $destination): Destination;
}
