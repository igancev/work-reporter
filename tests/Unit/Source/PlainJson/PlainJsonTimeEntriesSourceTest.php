<?php

declare(strict_types=1);

namespace Tests\Unit\Source\PlainJson;

use DateTimeImmutable;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\Source\PlainJson\PlainJsonTimeEntriesSource;
use Igancev\WorkReporter\Source\SourceException;
use Igancev\WorkReporter\TimeEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlainJsonTimeEntriesSource::class)]
class PlainJsonTimeEntriesSourceTest extends TestCase
{
    private string $jsonFilePath;

    protected function setUp(): void
    {
        $this->jsonFilePath = __DIR__ . '/../../../data/jsonList/jsonList.json';
    }

    public function testFetchTimeEntriesSuccessfully(): void
    {
        // Arrange
        $source = new PlainJsonTimeEntriesSource($this->jsonFilePath);
        $from = new DateTimeImmutable('2026-02-04');
        $to = new DateTimeImmutable('2026-02-05');

        // Act
        /** @var TimeEntry[] $timeEntries */
        $timeEntries = [...$source->fetchTimeEntries($from, $to)];

        // Assert
        $this->assertCount(5, $timeEntries);

        $this->assertEquals(
            new TimeEntry(
                taskId: 'DLS-111',
                duration: Duration::fromString('2h15m'),
                workType: 'Development',
                date: new DateTimeImmutable('2026-02-04'),
                comment: 'Create new component',
            ),
            $timeEntries[0]
        );

        $this->assertEquals(
            new TimeEntry(
                taskId: 'DLS-333',
                duration: Duration::fromString('5h'),
                workType: 'Meeting',
                date: new DateTimeImmutable('2026-02-05'),
                comment: 'Daily',
            ),
            $timeEntries[4]
        );
    }

    public function testFetchTimeEntriesWithFiltering(): void
    {
        // Arrange
        $source = new PlainJsonTimeEntriesSource($this->jsonFilePath);
        $from = new DateTimeImmutable('2026-02-05');
        $to = new DateTimeImmutable('2026-02-05');

        // Act
        /** @var TimeEntry[] $timeEntries */
        $timeEntries = [...$source->fetchTimeEntries($from, $to)];

        // Assert
        $this->assertCount(2, $timeEntries);
        $this->assertEquals('DLS-333', $timeEntries[0]->taskId);
        $this->assertEquals('2026-02-05', $timeEntries[0]->date->format('Y-m-d'));
    }

    public function testFetchTimeEntriesFileNotFound(): void
    {
        // Arrange
        $source = new PlainJsonTimeEntriesSource('non_existent.json');
        $from = new DateTimeImmutable('2026-02-04');
        $to = new DateTimeImmutable('2026-02-05');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('File not found: non_existent.json');

        // Act
        $source->fetchTimeEntries($from, $to);
    }

    public function testFetchTimeEntriesInvalidJson(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_json');
        file_put_contents($tempFile, '{ "invalid": json }');
        $source = new PlainJsonTimeEntriesSource($tempFile);
        $from = new DateTimeImmutable('2026-02-04');
        $to = new DateTimeImmutable('2026-02-05');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Invalid JSON:');

        try {
            // Act
            $source->fetchTimeEntries($from, $to);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchTimeEntriesMissingTimeEntriesArray(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'missing_array');
        file_put_contents($tempFile, json_encode(['other' => []]));
        $source = new PlainJsonTimeEntriesSource($tempFile);
        $from = new DateTimeImmutable('2026-02-04');
        $to = new DateTimeImmutable('2026-02-05');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('JSON must contain a "timeEntries" array');

        try {
            // Act
            $source->fetchTimeEntries($from, $to);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchTimeEntriesMissingRequiredFields(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'missing_fields');
        file_put_contents($tempFile, json_encode([
            'timeEntries' => [
                ['date' => '2026-02-04'] // missing taskId, duration, workType
            ]
        ]));
        $source = new PlainJsonTimeEntriesSource($tempFile);
        $from = new DateTimeImmutable('2026-02-04');
        $to = new DateTimeImmutable('2026-02-05');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('TaskId is required');

        try {
            // Act
            $source->fetchTimeEntries($from, $to);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchTimeEntriesInvalidDateFormat(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_date');
        file_put_contents($tempFile, json_encode([
            'timeEntries' => [
                [
                    'date' => 'invalid-date',
                    'taskId' => 'DLS-111',
                    'duration' => '1h',
                    'workType' => 'Development'
                ]
            ]
        ]));
        $source = new PlainJsonTimeEntriesSource($tempFile);
        $from = new DateTimeImmutable('2026-02-04');
        $to = new DateTimeImmutable('2026-02-05');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Invalid date format: invalid-date');

        try {
            // Act
            $source->fetchTimeEntries($from, $to);
        } finally {
            unlink($tempFile);
        }
    }

    public function testFetchTimeEntriesCouldNotReadFile(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'unreadable');
        touch($tempFile);
        chmod($tempFile, 0000);
        $source = new PlainJsonTimeEntriesSource($tempFile);
        $from = new DateTimeImmutable('2026-02-04');
        $to = new DateTimeImmutable('2026-02-05');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('Could not read file:');

        try {
            // Act
            $source->fetchTimeEntries($from, $to);
        } finally {
            chmod($tempFile, 0644);
            unlink($tempFile);
        }
    }
}
