<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\TimeEntry;
use Igancev\WorkReporter\TimeEntryCollection;
use PHPUnit\Framework\TestCase;

class TimeEntryCollectionTest extends TestCase
{
    public function testAllReturnsAllItems(): void
    {
        // Arrange
        $entries = [
            new TimeEntry('T1', Duration::fromMinutes(60), 'Dev', new DateTimeImmutable('2023-01-01')),
            new TimeEntry('T2', Duration::fromMinutes(30), 'Meeting', new DateTimeImmutable('2023-01-01')),
        ];

        // Act
        $collection = new TimeEntryCollection($entries);

        // Assert
        $this->assertCount(2, $collection->all());
        $this->assertSame($entries, $collection->all());
    }

    public function testGroupedAggregatesEntries(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2023-01-01');
        $entries = [
            new TimeEntry('T1', Duration::fromMinutes(60), 'Dev', $date, 'Task 1 first part'),
            new TimeEntry('T1', Duration::fromMinutes(30), 'Dev', $date, 'Task 1 second part'),
            new TimeEntry('T2', Duration::fromMinutes(45), 'Meeting', $date, 'Meeting 1'),
            new TimeEntry('T1', Duration::fromMinutes(15), 'Review', $date, 'Reviewing T1'),
        ];

        $collection = new TimeEntryCollection($entries);

        // Act
        $grouped = $collection->grouped();

        // Assert
        $this->assertCount(3, $grouped);

        // Check T1 Dev
        $this->assertEquals('T1', $grouped[0]->taskId);
        $this->assertEquals(Duration::fromMinutes(90), $grouped[0]->duration);
        $this->assertEquals('Dev', $grouped[0]->workType);
        $this->assertEquals("- Task 1 first part\n- Task 1 second part", $grouped[0]->comment);

        // Check T1 Review
        $this->assertEquals('T1', $grouped[1]->taskId);
        $this->assertEquals(Duration::fromMinutes(15), $grouped[1]->duration);
        $this->assertEquals('Review', $grouped[1]->workType);
        $this->assertEquals("- Reviewing T1", $grouped[1]->comment);

        // Check T2 Meeting
        $this->assertEquals('T2', $grouped[2]->taskId);
        $this->assertEquals(Duration::fromMinutes(45), $grouped[2]->duration);
        $this->assertEquals('Meeting', $grouped[2]->workType);
        $this->assertEquals("- Meeting 1", $grouped[2]->comment);
    }

    public function testGroupedSortsByDate(): void
    {
        // Arrange
        $date1 = new DateTimeImmutable('2023-01-02');
        $date2 = new DateTimeImmutable('2023-01-01');

        $entries = [
            new TimeEntry('T1', Duration::fromMinutes(60), 'Dev', $date1),
            new TimeEntry('T2', Duration::fromMinutes(30), 'Dev', $date2),
        ];

        $collection = new TimeEntryCollection($entries);

        // Act
        $grouped = $collection->grouped();

        // Assert
        $this->assertCount(2, $grouped);
        $this->assertEquals('2023-01-01', $grouped[0]->date->format('Y-m-d'));
        $this->assertEquals('2023-01-02', $grouped[1]->date->format('Y-m-d'));
    }

    public function testCombinesDuplicateCommentsOnlyOnce(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2023-01-01');
        $entries = [
            new TimeEntry('T1', Duration::fromMinutes(30), 'Dev', $date, 'Work'),
            new TimeEntry('T1', Duration::fromMinutes(30), 'Dev', $date, 'Work'),
        ];

        $collection = new TimeEntryCollection($entries);

        // Act
        $grouped = $collection->grouped();

        // Assert
        $this->assertCount(1, $grouped);
        $this->assertEquals("- Work", $grouped[0]->comment);
    }
}
