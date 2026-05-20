<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\TimeEntry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeEntry::class)]
class TimeEntryTest extends TestCase
{
    public function testWorkTypeCannotBeEmpty(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Work type cannot be empty');

        // Act
        new TimeEntry(
            taskId: 'TASK-1',
            duration: Duration::fromString('1h'),
            workType: '', // given empty work type
            date: new DateTimeImmutable('2026-01-01'),
            comment: 'comment'
        );
    }

    public function testWorkTypeCannotBeWhitespaceOnly(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Work type cannot be empty');

        // Act
        new TimeEntry(
            taskId: 'TASK-1',
            duration: Duration::fromString('1h'),
            workType: '   ',
            date: new DateTimeImmutable('2026-01-01'),
        );
    }
}
