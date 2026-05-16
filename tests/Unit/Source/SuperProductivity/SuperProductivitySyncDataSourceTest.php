<?php

declare(strict_types=1);

namespace Tests\Unit\Source\SuperProductivity;

use DateTimeImmutable;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\Source\SuperProductivity\SuperProductivitySyncSource;
use Igancev\WorkReporter\TimeEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuperProductivitySyncSource::class)]
class SuperProductivitySyncDataSourceTest extends TestCase
{
    private string $metaFilePath;

    protected function setUp(): void
    {
        $this->metaFilePath = __DIR__ . '/../../../data/superproductivity/__meta_';
    }

    public function testGetTimeEntries(): void
    {
        // Arrange
        $dataSource = new SuperProductivitySyncSource($this->metaFilePath);

        $from = new DateTimeImmutable('2026-02-08');
        $to = new DateTimeImmutable('2026-02-08');

        // Act
        $timeEntries = $dataSource->fetchTimeEntries($from, $to);

        // Asserts
        $this->assertCount(10, $timeEntries);

        // DLS-111 - task without subtasks
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-111',
                duration: Duration::fromString('1h'),
                workType: 'Ревью',
                date: new DateTimeImmutable('2026-02-08'),
                comment: '',
            ),
            $timeEntries,
        );
        // DLS-222 - task with subtasks
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-222',
                duration: Duration::fromString('10m'),
                workType: 'Подготовка задач',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 10 минут',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-222',
                duration: Duration::fromString('20m'),
                workType: 'Встречи',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 20 минут',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-222',
                duration: Duration::fromString('30m'),
                workType: 'Разработка',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 30 минут',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-222',
                duration: Duration::fromString('1h'),
                workType: 'Ревью',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 1 час',
            ),
            $timeEntries,
        );
        // DLS-333
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-333',
                duration: Duration::fromString('1h'),
                workType: 'Встречи',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 1 час',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-333',
                duration: Duration::fromString('2h'),
                workType: 'Подзадача',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 2 часа',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-333',
                duration: Duration::fromString('30m'),
                workType: 'Подготовка задач',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 30 минут',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'DLS-333',
                duration: Duration::fromString('15m'),
                workType: 'Ревью',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 15 минут',
            ),
            $timeEntries,
        );
    }

    public function testGetTimeEntriesEmptyRange(): void
    {
        // Arrange
        $dataSource = new SuperProductivitySyncSource($this->metaFilePath);
        $from = new DateTimeImmutable('2026-02-07');
        $to = new DateTimeImmutable('2026-02-07');

        // Act
        $timeEntries = $dataSource->fetchTimeEntries($from, $to);

        // Assert
        $this->assertEmpty($timeEntries);
    }

    public function testGetTimeEntriesInvalidRange(): void
    {
        // Arrange
        $dataSource = new SuperProductivitySyncSource($this->metaFilePath);
        $from = new DateTimeImmutable('2026-02-08');
        $to = new DateTimeImmutable('2026-02-07');

        // Act
        $timeEntries = $dataSource->fetchTimeEntries($from, $to);

        // Assert
        $this->assertEmpty($timeEntries);
    }
}
