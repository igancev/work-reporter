<?php

declare(strict_types=1);

namespace Tests\Unit\Destination\YouTrack;

use Igancev\WorkReporter\Destination\YouTrack\TaskId;
use Igancev\WorkReporter\Destination\YouTrack\TaskIdCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskIdCollection::class)]
final class TaskIdCollectionTest extends TestCase
{
    public function testToArray(): void
    {
        // Arrange
        $taskId1 = TaskId::fromString('PRJ-1');
        $taskId2 = TaskId::fromString('PRJ-2');
        $taskIds = [$taskId1, $taskId2];
        $collection = new TaskIdCollection($taskIds);

        // Act
        $result = $collection->toArray();

        // Assert
        $this->assertSame($taskIds, $result);
    }

    public function testFilterUniqueByProject(): void
    {
        // Arrange
        $taskId1 = TaskId::fromString('PRJ-1');
        $taskId2 = TaskId::fromString('PRJ-2');
        $taskId3 = TaskId::fromString('OTHER-1');
        $taskId4 = TaskId::fromString('PRJ-3');
        $taskIds = [$taskId1, $taskId2, $taskId3, $taskId4];
        $collection = new TaskIdCollection($taskIds);

        // Act
        $filtered = $collection->filterUniqueByProject();

        // Assert
        $result = $filtered->toArray();
        $this->assertCount(2, $result);
        $this->assertSame($taskId1, $result[0]); // First one for PRJ
        $this->assertSame($taskId3, $result[1]); // First one for OTHER
    }
}
