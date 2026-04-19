<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use Igancev\WorkReporter\TimeEntry;

final readonly class BatchDeliveryResult
{
    /**
     * @param TimeEntry[] $successDelivered
     * @param DeliveryFailure[] $failures
     */
    public function __construct(
        private array $successDelivered,
        private array $failures,
    ) {
    }

    public function isSuccessful(): bool
    {
        return count($this->failures) === 0;
    }

    public function successfulCount(): int
    {
        return count($this->successDelivered);
    }

    public function failuresCount(): int
    {
        return count($this->failures);
    }

    /**
     * @return TimeEntry[]
     */
    public function successDelivered(): array
    {
        return $this->successDelivered;
    }

    /**
     * @return DeliveryFailure[]
     */
    public function failures(): array
    {
        return $this->failures;
    }
}
