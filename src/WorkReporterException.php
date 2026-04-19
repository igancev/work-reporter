<?php

declare(strict_types=1);

namespace Igancev\WorkReporter;

use Exception;
use Throwable;

/**
 * @template TContext of array<string, scalar|array|null>
 */
abstract class WorkReporterException extends Exception
{
    public function __construct(
        string $message,
        /** @var TContext $context */
        private readonly array $context = [],
        ?Throwable $previous = null,
        int $code = 0,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return TContext
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
