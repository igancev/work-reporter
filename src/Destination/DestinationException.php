<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use Igancev\WorkReporter\WorkReporterException;

/**
 * @api
 * @extends WorkReporterException<array<string, scalar|array|null>>
 */
final class DestinationException extends WorkReporterException
{
}
