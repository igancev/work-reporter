<?php

declare(strict_types=1);

namespace Tests\Unit\Destination\YouTrack;

use Igancev\WorkReporter\Destination\YouTrack\Project;
use Igancev\WorkReporter\Destination\YouTrack\WorkItemType;
use Igancev\WorkReporter\Destination\YouTrack\WorkItemTypeCollection;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkItemTypeCollection::class)]
final class WorkItemTypeCollectionTest extends TestCase
{
    public function testGetByProjectAndNameSuccess(): void
    {
        // Arrange
        $type1 = new WorkItemType('id1', 'Development', 'p1');
        $type2 = new WorkItemType('id2', 'Research', 'p1');
        $type3 = new WorkItemType('id3', 'Bug', 'p2');

        $collection = new WorkItemTypeCollection([$type1, $type2, $type3]);
        $project = new Project('p1', 'Project 1', 'PRJ');

        // Act
        $result = $collection->getByProjectAndName($project, 'development');

        // Assert
        $this->assertSame($type1, $result);
    }

    public function testGetByProjectAndNameThrowsExceptionIfProjectNotFound(): void
    {
        // Arrange
        $type = new WorkItemType('id1', 'Development', 'p1');
        $collection = new WorkItemTypeCollection([$type]);
        $project = new Project('unknown', 'Unknown Project', 'UNK');

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Project with id 'unknown' not found");

        // Act
        $collection->getByProjectAndName($project, 'Development');
    }

    public function testGetByProjectAndNameThrowsExceptionIfTypeNotFound(): void
    {
        // Arrange
        $type = new WorkItemType('id1', 'Development', 'p1');
        $collection = new WorkItemTypeCollection([$type]);
        $project = new Project('p1', 'Project 1', 'PRJ');

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('WorkItemType "Testing" not found in project "Project 1"');

        // Act
        $collection->getByProjectAndName($project, 'Testing');
    }
}
