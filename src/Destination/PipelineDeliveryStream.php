<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use Amp\Pipeline\Pipeline;
use Traversable;

/**
 * @template T of DeliveryEvent
 * @implements DeliveryStream<T>
 */
final readonly class PipelineDeliveryStream implements DeliveryStream
{
    /**
     * @param Pipeline<T> $pipeline
     */
    public function __construct(
        private Pipeline $pipeline,
    ) {
    }

    public function getIterator(): Traversable
    {
        return $this->pipeline->getIterator();
    }
}
