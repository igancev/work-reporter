<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use IteratorAggregate;

/**
 * @template T of DeliveryEvent
 * @extends IteratorAggregate<int, T>
 */
interface DeliveryStream extends IteratorAggregate
{
}
