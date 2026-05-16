<?php

declare(strict_types=1);

namespace Tests\Unit\Source\SuperProductivity;

use Igancev\WorkReporter\Source\SuperProductivity\Storage;
use Igancev\WorkReporter\Source\SuperProductivity\Tag;
use Igancev\WorkReporter\Source\SuperProductivity\TaskFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskFactory::class)]
#[AllowMockObjectsWithoutExpectations]
final class TaskFactoryTest extends TestCase
{
    private Storage&MockObject $storage;
    private TaskFactory $factory;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(Storage::class);
        $this->factory = new TaskFactory($this->storage);
    }

    public function testFromTaskIdSimpleTask(): void
    {
        // Arrange
        $taskId = 'task-1';
        $rawTask = [
            'id' => $taskId,
            'title' => 'Simple Task',
            'tagIds' => [],
            'timeSpentOnDay' => ['2026-03-10' => 3600000],
            'subTaskIds' => [],
            'parentId' => null,
        ];

        $this->storage->expects($this->once())
            ->method('getTaskById')
            ->with($taskId)
            ->willReturn($rawTask);

        // Act
        $task = $this->factory->fromTaskId($taskId);

        // Assert
        $this->assertSame($taskId, $task->id);
        $this->assertSame('Simple Task', $task->title);
        $this->assertNull($task->parentTitle);
        $this->assertEmpty($task->subTasks);
        $this->assertEmpty($task->tags);
        $this->assertSame(['2026-03-10' => 3600000], $task->timeSpentOnDay);
    }

    public function testFromTaskIdWithTags(): void
    {
        // Arrange
        $taskId = 'task-1';
        $tagId = 'tag-1';
        $rawTask = [
            'id' => $taskId,
            'title' => 'Task with Tags',
            'tagIds' => [$tagId],
            'timeSpentOnDay' => [],
            'subTaskIds' => [],
            'parentId' => null,
        ];

        $tag = new Tag($tagId, 'Work');

        $this->storage->expects($this->once())
            ->method('getTaskById')
            ->with($taskId)
            ->willReturn($rawTask);

        $this->storage->expects($this->once())
            ->method('getTagById')
            ->with($tagId)
            ->willReturn($tag);

        // Act
        $task = $this->factory->fromTaskId($taskId);

        // Assert
        $this->assertCount(1, $task->tags);
        $this->assertSame($tag, $task->tags[0]);
    }

    public function testFromTaskIdWithParent(): void
    {
        // Arrange
        $taskId = 'subtask-1';
        $parentId = 'parent-1';
        $rawSubtask = [
            'id' => $taskId,
            'title' => 'Subtask',
            'tagIds' => [],
            'timeSpentOnDay' => [],
            'subTaskIds' => [],
            'parentId' => $parentId,
        ];

        $rawParent = [
            'id' => $parentId,
            'title' => 'Parent Task',
        ];

        $this->storage->expects($this->any())
            ->method('getTaskById')
            ->willReturnMap([
                [$taskId, $rawSubtask],
                [$parentId, $rawParent],
            ]);

        // Act
        $task = $this->factory->fromTaskId($taskId);

        // Assert
        $this->assertSame('Parent Task', $task->parentTitle);
        $this->assertTrue($task->isSubtask());
    }

    public function testFromTaskIdWithSubtasks(): void
    {
        // Arrange
        $parentTaskId = 'parent-1';
        $subTaskId = 'sub-1';

        $rawParent = [
            'id' => $parentTaskId,
            'title' => 'Parent Task',
            'tagIds' => [],
            'timeSpentOnDay' => [],
            'subTaskIds' => [$subTaskId],
            'parentId' => null,
        ];

        $rawSubTask = [
            'id' => $subTaskId,
            'title' => 'Subtask',
            'tagIds' => [],
            'timeSpentOnDay' => [],
            'subTaskIds' => [],
            'parentId' => $parentTaskId,
        ];

        $this->storage->expects($this->any())
            ->method('getTaskById')
            ->willReturnMap([
                [$parentTaskId, $rawParent],
                [$subTaskId, $rawSubTask],
            ]);

        // Act
        $task = $this->factory->fromTaskId($parentTaskId);

        // Assert
        $this->assertCount(1, $task->subTasks);
        $this->assertSame($subTaskId, $task->subTasks[0]->id);
        $this->assertSame('Parent Task', $task->subTasks[0]->parentTitle);
    }
}
