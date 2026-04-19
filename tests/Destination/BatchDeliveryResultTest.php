<?php

declare(strict_types=1);

namespace Tests\Destination;

use DateTimeImmutable;
use Igancev\WorkReporter\Destination\BatchDeliveryResult;
use Igancev\WorkReporter\Destination\DeliveryFailure;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\TimeEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(BatchDeliveryResult::class)]
final class BatchDeliveryResultTest extends TestCase
{
    public function testSuccessfulResult(): void
    {
        // Arrange
        $entries = [
            $this->createEntry('task-1'),
            $this->createEntry('task-2'),
        ];
        $failures = [];

        // Act
        $result = new BatchDeliveryResult($entries, $failures);

        // Assert
        $this->assertTrue($result->isSuccessful());
        $this->assertSame(2, $result->successfulCount());
        $this->assertSame(0, $result->failuresCount());
        $this->assertSame($entries, $result->successDelivered());
        $this->assertSame($failures, $result->failures());
    }

    public function testPartialFailureResult(): void
    {
        // Arrange
        $entries = [$this->createEntry('task-1')];
        $failureEntry = $this->createEntry('task-2');
        $failures = [
            new DeliveryFailure($failureEntry, new RuntimeException('API Error')),
        ];

        // Act
        $result = new BatchDeliveryResult($entries, $failures);

        // Assert
        $this->assertFalse($result->isSuccessful());
        $this->assertSame(1, $result->successfulCount());
        $this->assertSame(1, $result->failuresCount());
        $this->assertSame($entries, $result->successDelivered());
        $this->assertSame($failures, $result->failures());
        $this->assertSame($failureEntry, $result->failures()[0]->timeEntry);
    }

    public function testTotalFailureResult(): void
    {
        // Arrange
        $entries = [];
        $failures = [
            new DeliveryFailure($this->createEntry('task-1'), new RuntimeException('Error 1')),
            new DeliveryFailure($this->createEntry('task-2'), new RuntimeException('Error 2')),
        ];

        // Act
        $result = new BatchDeliveryResult($entries, $failures);

        // Assert
        $this->assertFalse($result->isSuccessful());
        $this->assertSame(0, $result->successfulCount());
        $this->assertSame(2, $result->failuresCount());
        $this->assertEmpty($result->successDelivered());
        $this->assertCount(2, $result->failures());
    }

    private function createEntry(string $taskId): TimeEntry
    {
        return new TimeEntry(
            $taskId,
            Duration::fromMinutes(30),
            'Development',
            new DateTimeImmutable('2026-03-10'),
            'Coding something'
        );
    }
}
