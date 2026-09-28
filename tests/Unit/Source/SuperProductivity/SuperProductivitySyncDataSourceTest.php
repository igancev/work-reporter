<?php

declare(strict_types=1);

namespace Tests\Unit\Source\SuperProductivity;

use DateTimeImmutable;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\Source\SuperProductivity\SuperProductivitySyncSource;
use Igancev\WorkReporter\TimeEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuperProductivitySyncSource::class)]
class SuperProductivitySyncDataSourceTest extends TestCase
{
    private const string DATA_DIR = __DIR__ . '/../../../data/superproductivity';
    private const string SYNC_V1_FILE = self::DATA_DIR . '/__meta_';
    private const string SYNC_V2_FILE = self::DATA_DIR . '/sync-data-v2.json';

    /**
     * Both sync file formats must produce identical time entries for the same task data.
     */
    #[DataProvider('syncDataFilePathsProvider')]
    public function testGetTimeEntries(string $syncDataFilePath): void
    {
        // Arrange
        $dataSource = new SuperProductivitySyncSource($syncDataFilePath);

        $from = new DateTimeImmutable('2026-02-08');
        $to = new DateTimeImmutable('2026-02-08');

        // Act
        $timeEntries = $dataSource->fetchTimeEntries($from, $to);

        // Asserts
        $this->assertCount(10, $timeEntries);

        // PROJ-111 - task without subtasks
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-111',
                duration: Duration::fromString('1h'),
                workType: 'Ревью',
                date: new DateTimeImmutable('2026-02-08'),
                comment: '',
            ),
            $timeEntries,
        );
        // PROJ-222 - task with subtasks
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-222',
                duration: Duration::fromString('10m'),
                workType: 'Подготовка задач',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 10 минут',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-222',
                duration: Duration::fromString('20m'),
                workType: 'Встречи',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 20 минут',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-222',
                duration: Duration::fromString('30m'),
                workType: 'Разработка',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 30 минут',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-222',
                duration: Duration::fromString('1h'),
                workType: 'Ревью',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 1 час',
            ),
            $timeEntries,
        );
        // PROJ-333
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-333',
                duration: Duration::fromString('1h'),
                workType: 'Встречи',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 1 час',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-333',
                duration: Duration::fromString('2h'),
                workType: 'Подзадача',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 2 часа',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-333',
                duration: Duration::fromString('30m'),
                workType: 'Подготовка задач',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 30 минут',
            ),
            $timeEntries,
        );
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-333',
                duration: Duration::fromString('15m'),
                workType: 'Ревью',
                date: new DateTimeImmutable('2026-02-08'),
                comment: 'Подзадача 15 минут',
            ),
            $timeEntries,
        );
    }

    public function testGetTimeEntriesIncludesArchivedTasks(): void
    {
        // Arrange
        $dataSource = new SuperProductivitySyncSource(self::SYNC_V2_FILE);
        $from = new DateTimeImmutable('2026-02-06');
        $to = new DateTimeImmutable('2026-02-07');

        // Act
        $timeEntries = $dataSource->fetchTimeEntries($from, $to);

        // Assert
        $this->assertCount(2, $timeEntries);
        // PROJ-555 - plain task from archiveOld
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-555',
                duration: Duration::fromString('15m'),
                workType: 'Разработка',
                date: new DateTimeImmutable('2026-02-06'),
                comment: '',
            ),
            $timeEntries,
        );
        // PROJ-444 - subtask of a parent from archiveYoung (task id is parsed from the parent title)
        $this->assertContainsEquals(
            new TimeEntry(
                taskId: 'PROJ-444',
                duration: Duration::fromString('30m'),
                workType: 'Встречи',
                date: new DateTimeImmutable('2026-02-07'),
                comment: 'Архивная подзадача',
            ),
            $timeEntries,
        );
    }

    public function testGetTimeEntriesEmptyRange(): void
    {
        // Arrange
        $dataSource = new SuperProductivitySyncSource(self::SYNC_V1_FILE);
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
        $dataSource = new SuperProductivitySyncSource(self::SYNC_V1_FILE);
        $from = new DateTimeImmutable('2026-02-08');
        $to = new DateTimeImmutable('2026-02-07');

        // Act
        $timeEntries = $dataSource->fetchTimeEntries($from, $to);

        // Assert
        $this->assertEmpty($timeEntries);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function syncDataFilePathsProvider(): array
    {
        return [
            'legacy __meta_ (sync format v1)' => [self::SYNC_V1_FILE],
            'sync-data-v2 (sync format v2)' => [self::SYNC_V2_FILE],
        ];
    }
}
