<?php

declare(strict_types=1);

namespace Tests;

use DateTimeImmutable;
use Exception;
use Igancev\WorkReporter\Destination\BatchDeliveryResult;
use Igancev\WorkReporter\Destination\DeliveryFailure;
use Igancev\WorkReporter\Destination\Destination;
use Igancev\WorkReporter\Destination\DestinationException;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\Source\SourceException;
use Igancev\WorkReporter\Source\TimeEntriesSource;
use Igancev\WorkReporter\TimeEntry;
use Igancev\WorkReporter\WorkReportCommand;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(WorkReportCommand::class)]
final class WorkReportCommandTest extends TestCase
{
    private TimeEntriesSource&MockObject $source;
    private Destination&MockObject $destination;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->source = $this->createMock(TimeEntriesSource::class);
        $this->destination = $this->createMock(Destination::class);
        $command = new WorkReportCommand($this->source, $this->destination);
        $this->tester = new CommandTester($command);
    }

    public function testInvalidDateFormatThrowsException(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format: "invalid-date". Expected YYYY-MM-DD.');

        // Act
        $this->tester->execute(['--from' => 'invalid-date']);
    }

    public function testDefaultDatesAreToday(): void
    {
        // Arrange
        $today = new DateTimeImmutable()->format('Y-m-d');

        $this->source->expects($this->once())
            ->method('fetchTimeEntries')
            ->with(
                $this->callback(fn($d) => $d->format('Y-m-d') === $today),
                $this->callback(fn($d) => $d->format('Y-m-d') === $today)
            )
            ->willReturn([]);

        // Act
        $this->tester->execute([]);

        // Assert
        $this->assertStringContainsString('No time entries found', $this->tester->getDisplay());
    }

    public function testSourceExceptionReturnsFailure(): void
    {
        // Arrange
        $this->source->method('fetchTimeEntries')
            ->willThrowException(new SourceException('Source error'));

        // Act
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Source error', $this->tester->getDisplay());
    }

    public function testNoEntriesFoundReturnsSuccess(): void
    {
        // Arrange
        $this->source->method('fetchTimeEntries')->willReturn([]);

        // Act
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('No time entries found', $this->tester->getDisplay());
    }

    public function testFilterByMinDuration(): void
    {
        // Arrange
        $entry1 = new TimeEntry('T1', Duration::fromString('5m'), 'Dev', new DateTimeImmutable());
        $entry2 = new TimeEntry('T2', Duration::fromString('10m'), 'Dev', new DateTimeImmutable());

        $this->source->method('fetchTimeEntries')->willReturn([$entry1, $entry2]);

        // Act
        // min-duration = 10m, so T1 should be filtered out
        $this->tester->setInputs(['no']);
        $this->tester->execute(['--min-duration' => '10m']);

        // Assert
        $display = $this->tester->getDisplay();
        $this->assertStringNotContainsString('T1', $display);
        $this->assertStringContainsString('T2', $display);
    }

    public function testNoGrouping(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2026-01-01');
        $entry1 = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', $date, 'Comment 1');
        $entry2 = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', $date, 'Comment 2');

        $this->source->method('fetchTimeEntries')->willReturn([$entry1, $entry2]);

        // Act
        $this->tester->setInputs(['no']);
        $this->tester->execute(['--no-group' => true]);

        // Assert
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('Comment 1', $display);
        $this->assertStringContainsString('Comment 2', $display);
        // Without grouping, each entry keeps its own duration displayed separately
        // The table should show two separate rows with "1h" each
        $this->assertSame(2, substr_count($display, '1h'));
    }

    public function testGrouping(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2026-01-01');
        $entry1 = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', $date, 'Comment 1');
        $entry2 = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', $date, 'Comment 2');

        $this->source->method('fetchTimeEntries')->willReturn([$entry1, $entry2]);

        // Act
        $this->tester->setInputs(['no']);
        $this->tester->execute(['--group' => true]);

        // Assert
        $display = $this->tester->getDisplay();
        // Grouped: durations summed to 2h
        $this->assertStringContainsString('2h', $display);
        // Grouped: comments combined with "- " prefix
        $this->assertStringContainsString('- Comment 1', $display);
        $this->assertStringContainsString('- Comment 2', $display);
    }

    public function testAllEntriesFilteredOutReturnsSuccess(): void
    {
        // Arrange
        $entry = new TimeEntry('T1', Duration::fromString('5m'), 'Dev', new DateTimeImmutable());
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        // Act
        $status = $this->tester->execute(['--min-duration' => '10m']);

        // Assert
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('No time entries remain after filtering', $this->tester->getDisplay());
    }

    public function testDailyGoalUnderGoalShowsError(): void
    {
        // Arrange
        $entry = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', new DateTimeImmutable('2026-01-01'));
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        // Act
        $this->tester->setInputs(['no']);
        $this->tester->execute(['--daily-goal' => '8h'], ['decorated' => true]);

        // Assert
        $display = $this->tester->getDisplay(true);
        $this->assertStringContainsString('Total for 2026-01-01:', $display);
        // Under goal: SymfonyStyle wraps with <error> tag, which produces ANSI escape codes
        $this->assertMatchesRegularExpression('/\x1b\[.*1h.*\x1b\[/', $display);
    }

    public function testDailyGoalMetShowsInfo(): void
    {
        // Arrange
        $entry = new TimeEntry('T1', Duration::fromString('8h'), 'Dev', new DateTimeImmutable('2026-01-01'));
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        // Act
        $this->tester->setInputs(['no']);
        $this->tester->execute(['--daily-goal' => '7h'], ['decorated' => true]);

        // Assert
        $display = $this->tester->getDisplay(true);
        $this->assertStringContainsString('Total for 2026-01-01:', $display);
        $this->assertStringContainsString('8h', $display);
    }

    public function testConfirmNoCancelsSending(): void
    {
        // Arrange
        $entry = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', new DateTimeImmutable());
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);
        $this->destination->expects($this->never())->method('logTimeEntries');

        // Act
        $this->tester->setInputs(['no']);
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('Sending cancelled.', $this->tester->getDisplay());
    }

    public function testFullSuccessReporting(): void
    {
        // Arrange
        $entry = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', new DateTimeImmutable());
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        $this->destination->method('logTimeEntries')
            ->willReturn(new BatchDeliveryResult([$entry], []));

        // Act
        $this->tester->setInputs(['yes']);
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('All 1 time entries imported successfully!', $this->tester->getDisplay());
    }

    public function testPartialSuccessReporting(): void
    {
        // Arrange
        $entry1 = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', new DateTimeImmutable());
        $entry2 = new TimeEntry('T2', Duration::fromString('1h'), 'Dev', new DateTimeImmutable());
        $this->source->method('fetchTimeEntries')->willReturn([$entry1, $entry2]);

        $failure = new DeliveryFailure($entry2, new Exception('Api Error'));
        $this->destination->method('logTimeEntries')
            ->willReturn(new BatchDeliveryResult([$entry1], [$failure]));

        // Act
        $this->tester->setInputs(['yes']);
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $status);
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('Partial Success', $display);
        $this->assertStringContainsString('Successfully imported 1 entries.', $display);
        $this->assertStringContainsString('Failures', $display);
        $this->assertStringContainsString('Failed to import 1 entries:', $display);
        $this->assertStringContainsString('Api Error', $display);
    }

    public function testInvalidToDateFormatThrowsException(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format: "31-12-2026". Expected YYYY-MM-DD.');

        // Act
        $this->tester->execute(['--to' => '31-12-2026']);
    }

    public function testFilterByMinDurationBoundaryEqual(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2026-01-01');
        $entry = new TimeEntry('T1', Duration::fromString('10m'), 'Dev', $date);

        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        // Act
        $this->tester->setInputs(['no']);
        $this->tester->execute(['--min-duration' => '10m']);

        // Assert
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('T1', $display);
    }

    public function testFullFailureReporting(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2026-01-01');
        $entry = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', $date);
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        $failure = new DeliveryFailure($entry, new Exception('Server error'));
        $this->destination->method('logTimeEntries')
            ->willReturn(new BatchDeliveryResult([], [$failure]));

        // Act
        $this->tester->setInputs(['yes']);
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $status);
        $display = $this->tester->getDisplay();
        $this->assertStringNotContainsString('Partial Success', $display);
        $this->assertStringContainsString('Failures', $display);
        $this->assertStringContainsString('Failed to import 1 entries:', $display);
        $this->assertStringContainsString('Server error', $display);
    }

    public function testEmptyMinDurationFallsBackToDefault(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2026-01-01');
        // Entry with 30s duration (0.5m) — should be filtered out by default 1m
        $shortEntry = new TimeEntry('T1', Duration::fromMilliseconds(30000), 'Dev', $date);
        $normalEntry = new TimeEntry('T2', Duration::fromString('5m'), 'Dev', $date);

        $this->source->method('fetchTimeEntries')->willReturn([$shortEntry, $normalEntry]);

        // Act
        $this->tester->setInputs(['no']);
        $this->tester->execute(['--min-duration' => '']);

        // Assert
        $display = $this->tester->getDisplay();
        $this->assertStringNotContainsString('T1', $display);
        $this->assertStringContainsString('T2', $display);
    }

    public function testInvalidDailyGoalParsedAsZero(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2026-01-01');
        $entry = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', $date);
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        // Act
        // "abc" is not a valid duration, Duration::fromString returns 0m
        $this->tester->setInputs(['no']);
        $this->tester->execute(['--daily-goal' => 'abc']);

        // Assert
        // 1h >= 0m goal, so total should be displayed with info tag (not error)
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('Total for 2026-01-01:', $display);
        $this->assertStringContainsString('1h', $display);
    }

    public function testDestinationExceptionReturnsFailure(): void
    {
        // Arrange
        $entry = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', new DateTimeImmutable());
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        $this->destination->method('logTimeEntries')
            ->willThrowException(new DestinationException('Connection lost'));

        // Act
        $this->tester->setInputs(['yes']);
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Connection lost', $this->tester->getDisplay());
    }
}
