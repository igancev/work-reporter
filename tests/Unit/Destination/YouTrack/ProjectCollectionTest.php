<?php

declare(strict_types=1);

namespace Tests\Unit\Destination\YouTrack;

use Igancev\WorkReporter\Destination\YouTrack\Project;
use Igancev\WorkReporter\Destination\YouTrack\ProjectCollection;
use Igancev\WorkReporter\Destination\YouTrack\TaskId;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectCollection::class)]
final class ProjectCollectionTest extends TestCase
{
    public function testGetProjects(): void
    {
        // Arrange
        $projects = [
            new Project('id-1', 'Project 1', 'P1'),
            new Project('id-2', 'Project 2', 'P2'),
        ];
        $collection = new ProjectCollection($projects);

        // Act
        $result = $collection->getProjects();

        // Assert
        $this->assertSame($projects, $result);
    }

    public function testProjectByTaskIdReturnsCorrectProject(): void
    {
        // Arrange
        $project1 = new Project('id-1', 'Project 1', 'P1');
        $project2 = new Project('id-2', 'Project 2', 'P2');
        $collection = new ProjectCollection([$project1, $project2]);
        $taskId = TaskId::fromString('P2-123');

        // Act
        $result = $collection->projectByTaskId($taskId);

        // Assert
        $this->assertSame($project2, $result);
    }

    public function testProjectByTaskIdThrowsExceptionWhenProjectNotFound(): void
    {
        // Arrange
        $project1 = new Project('id-1', 'Project 1', 'P1');
        $collection = new ProjectCollection([$project1]);
        $taskId = TaskId::fromString('UNKNOWN-123');

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Project with alias "UNKNOWN" not found');

        // Act
        $collection->projectByTaskId($taskId);
    }
}
