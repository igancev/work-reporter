<?php

declare(strict_types=1);

namespace Tests;

use ArrayIterator;
use DateTimeImmutable;
use Exception;
use Igancev\WorkReporter\Config\Config;
use Igancev\WorkReporter\Config\ConfigException;
use Igancev\WorkReporter\Config\ConfigProvider;
use Igancev\WorkReporter\Config\DestinationConfig\DestinationsConfig;
use Igancev\WorkReporter\Config\SourceConfig\SourcesConfig;
use Igancev\WorkReporter\Destination\DeliveryEvent;
use Igancev\WorkReporter\Destination\DeliveryStream;
use Igancev\WorkReporter\Destination\Destination;
use Igancev\WorkReporter\Destination\DestinationException;
use Igancev\WorkReporter\Destination\DestinationFactory;
use Igancev\WorkReporter\Destination\DestinationType;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\Source\SourceException;
use Igancev\WorkReporter\Source\SourceType;
use Igancev\WorkReporter\Source\TimeEntriesSource;
use Igancev\WorkReporter\Source\TimeEntriesSourceFactory;
use Igancev\WorkReporter\TimeEntry;
use Igancev\WorkReporter\WorkReportCommand;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Traversable;

#[CoversClass(WorkReportCommand::class)]
#[AllowMockObjectsWithoutExpectations]
final class WorkReportCommandTest extends TestCase
{
    /** @var TimeEntriesSource&MockObject */
    private MockObject $source;
    /** @var Destination&MockObject */
    private MockObject $destination;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->source = $this->createMock(TimeEntriesSource::class);
        $this->destination = $this->createMock(Destination::class);

        $sourceFactory = $this->createStub(TimeEntriesSourceFactory::class);
        $sourceFactory->method('build')->willReturn($this->source);

        $destinationFactory = $this->createStub(DestinationFactory::class);
        $destinationFactory->method('build')->willReturn($this->destination);

        $config = new Config(
            SourceType::PlainJson,
            DestinationType::YouTrack,
            new SourcesConfig(),
            new DestinationsConfig(),
        );
        $configProvider = $this->createStub(ConfigProvider::class);
        $configProvider->method('get')->willReturn($config);

        $command = new WorkReportCommand($sourceFactory, $destinationFactory, $configProvider);
        $this->tester = new CommandTester($command);

        Prompt::interactive(false);
    }

    protected function tearDown(): void
    {
        Prompt::interactive();
        Prompt::fallbackWhen(false);
        ConfirmPrompt::fallbackUsing(fn() => true); // reset to default if needed
    }

    public function testInvalidFromDatePrintFormattedError(): void
    {
        // Act
        $this->tester->execute(['--from' => 'invalid-date']);

        // Assert
        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString(
            '[ERROR] Invalid date format: "invalid-date". Expected YYYY-MM-DD.',
            $display,
        );
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

        // Force "No" answer for confirm()
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);
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
        // Force "No" answer for confirm()
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);
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
        // Force "No" answer for confirm()
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);
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
        // Force "No" answer for confirm()
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);
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
        // Force "No" answer for confirm()
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);
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

        // Force "No" answer for confirm()
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);

        // Act
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
            ->willReturn($this->createDeliveryStream([
                new DeliveryEvent($entry, 10.0, true),
            ]));

        // Act
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

        $this->destination->method('logTimeEntries')
            ->willReturn($this->createDeliveryStream([
                new DeliveryEvent($entry1, 10.0, true),
                new DeliveryEvent($entry2, 10.0, false, new Exception('Api Error')),
            ]));

        // Act
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $status);
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('Partially completed: 1 of 2 entries were imported successfully.', $display);
        $this->assertStringContainsString('Api Error', $display);
    }

    public function testInvalidToDatePrintFormattedError(): void
    {
        // Act
        $this->tester->execute(['--to' => '31-12-2026']);

        // Assert
        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('[ERROR] Invalid date format: "31-12-2026". Expected YYYY-MM-DD.', $display);
    }

    public function testFilterByMinDurationBoundaryEqual(): void
    {
        // Arrange
        $date = new DateTimeImmutable('2026-01-01');
        $entry = new TimeEntry('T1', Duration::fromString('10m'), 'Dev', $date);

        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        // Act
        // Force "No" answer for confirm()
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);
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

        $this->destination->method('logTimeEntries')
            ->willReturn($this->createDeliveryStream([
                new DeliveryEvent($entry, 10.0, false, new Exception('Server error')),
            ]));

        // Act
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $status);
        $display = $this->tester->getDisplay();
        $this->assertStringNotContainsString('Partially completed', $display);
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
        // Force "No" answer for confirm()
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);
        $this->tester->execute(['--min-duration' => '']);

        // Assert
        $display = $this->tester->getDisplay();
        $this->assertStringNotContainsString('T1', $display);
        $this->assertStringContainsString('T2', $display);
    }

    public function testInvalidDailyGoalPrintFormattedError(): void
    {
        // Act
        $this->tester->execute(['--daily-goal' => 'abc']);

        // Assert
        $this->assertSame(Command::FAILURE, $this->tester->getStatusCode());
        $display = $this->tester->getDisplay();
        $this->assertStringContainsString('[ERROR] Invalid duration format: "abc"', $display);
    }

    public function testDestinationExceptionReturnsFailure(): void
    {
        // Arrange
        $entry = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', new DateTimeImmutable());
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        $this->destination->method('logTimeEntries')
            ->willThrowException(new DestinationException('Connection lost'));

        // Act
        $status = $this->tester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Connection lost', $this->tester->getDisplay());
    }
    public function testYesOptionSkipsConfirmation(): void
    {
        // Arrange
        $entry = new TimeEntry('T1', Duration::fromString('1h'), 'Dev', new DateTimeImmutable());
        $this->source->method('fetchTimeEntries')->willReturn([$entry]);

        // Expect logTimeEntries to BE called
        $this->destination->expects($this->once())
            ->method('logTimeEntries')
            ->willReturn($this->createDeliveryStream([
                new DeliveryEvent($entry, 10.0, true),
            ]));

        // In non-interactive mode it returns default (true) by default,
        // but we want to test specifically the --yes option, which should skip the confirm() call.
        // If confirm() is called and returns false, the test will fail.
        // We force confirm() to return false to ensure it is NOT called.
        ConfirmPrompt::fallbackUsing(fn() => false);
        Prompt::fallbackWhen(true);

        // Act
        $status = $this->tester->execute(['--yes' => true]);

        // Assert
        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('All 1 time entries imported successfully!', $this->tester->getDisplay());
    }

    public function testConfigExceptionReturnsFailure(): void
    {
        // Arrange
        $configProvider = $this->createMock(ConfigProvider::class);
        $configProvider->method('get')->willThrowException(new ConfigException('Config error'));

        $command = new WorkReportCommand(
            $this->createStub(TimeEntriesSourceFactory::class),
            $this->createStub(DestinationFactory::class),
            $configProvider
        );
        $tester = new CommandTester($command);

        // Act
        $status = $tester->execute([]);

        // Assert
        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Config error', $tester->getDisplay());
    }

    /**
     * @param DeliveryEvent[] $events
     * @return DeliveryStream<DeliveryEvent>
     */
    private function createDeliveryStream(array $events): DeliveryStream
    {
        return new readonly class ($events) implements DeliveryStream {
            /** @param DeliveryEvent[] $events */
            public function __construct(private array $events)
            {
            }

            public function getIterator(): Traversable
            {
                return new ArrayIterator($this->events);
            }
        };
    }
}
