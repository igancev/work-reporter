<?php

declare(strict_types=1);

namespace Igancev\WorkReporter;

use DateTimeImmutable;
use Igancev\WorkReporter\Config\ConfigException;
use Igancev\WorkReporter\Config\ConfigProvider;
use Igancev\WorkReporter\Destination\DeliveryEvent;
use Igancev\WorkReporter\Destination\DeliveryStream;
use Igancev\WorkReporter\Destination\Destination;
use Igancev\WorkReporter\Destination\DestinationException;
use Igancev\WorkReporter\Destination\DestinationFactory;
use Igancev\WorkReporter\Source\SourceException;
use Igancev\WorkReporter\Source\TimeEntriesSourceFactory;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\title;

#[AsCommand(
    name: 'work:report',
    description: 'Export and report your work time entries from various sources (JSON, SuperProductivity) ' .
    'to destination (task trackers like YouTrack).',
)]
class WorkReportCommand extends Command
{
    private DateTimeImmutable $from;
    private DateTimeImmutable $to;
    private SymfonyStyle $io;
    private bool $isGrouped;
    private Duration $dailyGoal;
    private Duration $minDuration;
    private Destination $destination;

    public function __construct(
        private readonly TimeEntriesSourceFactory $timeEntriesSourceFactory,
        private readonly DestinationFactory $destinationFactory,
        private readonly ConfigProvider $configProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDefinition(
            new InputDefinition([
                new InputOption(
                    name: 'from',
                    shortcut: 'f',
                    mode: InputOption::VALUE_REQUIRED,
                    description: 'Date from which to export time entries (inclusive).' .
                    'Format: YYYY-MM-DD. Default: today',
                    default: new DateTimeImmutable()->format('Y-m-d'),
                ),
                new InputOption(
                    name: 'to',
                    shortcut: 't',
                    mode: InputOption::VALUE_REQUIRED,
                    description: 'Date until which to export time entries (inclusive). ' .
                    'Format: YYYY-MM-DD. Default: today',
                    default: new DateTimeImmutable()->format('Y-m-d'),
                ),
                new InputOption(
                    name: 'group',
                    shortcut: 'g',
                    mode: InputOption::VALUE_NEGATABLE,
                    description: 'Whether to group time entries by task and work type.',
                    default: true,
                ),
                new InputOption(
                    name: 'daily-goal',
                    mode: InputOption::VALUE_REQUIRED,
                    description: 'Daily goal work duration (e.g. 7h, 420m, 7h 30m).',
                    default: '7h',
                ),
                new InputOption(
                    name: 'min-duration',
                    mode: InputOption::VALUE_REQUIRED,
                    description: 'Minimum duration to include (e.g. 1m, 5m, 1h). Entries shorter will be ignored.',
                    default: '1m',
                ),
                new InputOption(
                    name: 'yes',
                    shortcut: 'y',
                    mode: InputOption::VALUE_NONE,
                    description: 'Do not ask for confirmation and assume "yes" as the answer.',
                ),
            ])
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io = new SymfonyStyle($input, $output);

        $this->from = $this->parseDate($input->getOption('from'));
        $this->to = $this->parseDate($input->getOption('to'));
        $this->isGrouped = (bool)$input->getOption('group');
        $this->dailyGoal = Duration::fromString($input->getOption('daily-goal'));
        $this->minDuration = $this->parseMinDuration($input->getOption('min-duration'));
    }

    private function parseDate(string $dateStr): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if (!$date || $date->format('Y-m-d') !== $dateStr) {
            throw new InvalidArgumentException(sprintf('Invalid date format: "%s". Expected YYYY-MM-DD.', $dateStr));
        }

        return $date->setTime(0, 0);
    }

    private function parseMinDuration(string $value): Duration
    {
        if ($value === '') {
            return Duration::fromString('1m');
        }

        return Duration::fromString($value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        title('Work Reporter');

        try {
            $config = $this->configProvider->get();
        } catch (ConfigException $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        $timeEntriesSource = $this->timeEntriesSourceFactory->build($config->source);
        $this->destination = $this->destinationFactory->build($config->destination);

        try {
            $timeEntries = $timeEntriesSource->fetchTimeEntries($this->from, $this->to);
        } catch (SourceException $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        if (empty($timeEntries)) {
            $this->io->warning("No time entries found in the source: \"{$config->source->value}\"");
            return Command::SUCCESS;
        }

        $timeEntries = $this->filterByMinDuration((array)$timeEntries);

        if (empty($timeEntries)) {
            $this->io->warning('No time entries remain after filtering by minimum duration.');
            return Command::SUCCESS;
        }

        $timeEntries = new TimeEntryCollection($timeEntries);
        $finalTimeEntries = $this->isGrouped ? $timeEntries->grouped() : $timeEntries->all();

        $this->displayTimeEntries($finalTimeEntries);

        $shouldSend = $input->getOption('yes') ||
            confirm("Do you want to send these time entries to {$config->destination->value}?");

        if (!$shouldSend) {
            $this->io->note('Sending cancelled.');
            return Command::SUCCESS;
        }

        return $this->sendToDestination($finalTimeEntries);
    }

    /**
     * @param TimeEntry[] $timeEntries
     * @return TimeEntry[]
     */
    private function filterByMinDuration(array $timeEntries): array
    {
        return array_values(
            array_filter(
                $timeEntries,
                fn(TimeEntry $entry) => $entry->duration->isGreaterThan($this->minDuration)
                    || $entry->duration->equals($this->minDuration)
            )
        );
    }

    /**
     * @param TimeEntry[] $timeEntries
     */
    private function displayTimeEntries(array $timeEntries): void
    {
        $entriesByDate = [];
        foreach ($timeEntries as $entry) {
            $date = $entry->date->format('Y-m-d');
            $entriesByDate[$date][] = $entry;
        }

        foreach ($entriesByDate as $date => $entries) {
            $this->io->section("Date: $date");
            $tableData = [];
            $totalDuration = Duration::fromMinutes(0);

            foreach ($entries as $entry) {
                $tableData[] = [
                    $entry->taskId,
                    $entry->duration->toString(),
                    $entry->workType,
                    $entry->comment,
                ];
                $totalDuration = $totalDuration->add($entry->duration);
            }

            $this->io->table(
                ['Task ID', 'Duration', 'Work Type', 'Comment'],
                $tableData
            );

            $this->displayDailyTotal($date, $totalDuration);
            $this->io->newLine();
        }
    }

    private function displayDailyTotal(string $date, Duration $totalDuration): void
    {
        $isUnderTarget = $totalDuration->isLessThan($this->dailyGoal);
        $tag = $isUnderTarget ? 'error' : 'info';

        $this->io->text(
            sprintf('Total for %s: <%s>%s</%s>', $date, $tag, $totalDuration->toString(), $tag)
        );
    }

    /**
     * @param TimeEntry[] $finalTimeEntries
     */
    private function sendToDestination(array $finalTimeEntries): int
    {
        $destination = $this->destination;
        /** @var DeliveryEvent[] $successEvents */
        $successEvents = [];
        /** @var DeliveryEvent[] $failureEvents */
        $failureEvents = [];
        $deliveryException = null;

        $this->io->section(sprintf('Sending %d time entries...', count($finalTimeEntries)));

        $startTime = hrtime(true);

        try {
            /** @var DeliveryStream<DeliveryEvent> $stream */
            $stream = $destination->logTimeEntries($finalTimeEntries);

            /** @var DeliveryEvent $event */
            foreach ($stream as $event) {
                if ($event->success) {
                    $successEvents[] = $event;
                    $this->io->writeln(sprintf(
                        ' <info>✔</info> %s %s %s (%s) — delivered (%.0fms)',
                        $event->timeEntry->date->format('Y-m-d'),
                        $event->timeEntry->taskId,
                        $event->timeEntry->duration->toString(),
                        $event->timeEntry->workType,
                        $event->durationMs,
                    ));
                } else {
                    $failureEvents[] = $event;
                    $this->io->writeln(sprintf(
                        ' <error>✘</error> %s %s %s (%s) — %s (%.0fms)',
                        $event->timeEntry->date->format('Y-m-d'),
                        $event->timeEntry->taskId,
                        $event->timeEntry->duration->toString(),
                        $event->timeEntry->workType,
                        $event->error?->getMessage() ?? 'unknown error',
                        $event->durationMs,
                    ));
                }
            }
        } catch (DestinationException $e) {
            $deliveryException = $e;
        }

        $elapsedMs = (hrtime(true) - $startTime) / 1_000_000;
        $this->io->writeln(sprintf(' Total time: <comment>%.0fms</comment>', $elapsedMs));
        $this->io->newLine();

        if ($deliveryException !== null) {
            $this->io->section('Delivery Failed');
            $this->io->error($deliveryException->getMessage());
            return Command::FAILURE;
        }

        if (count($failureEvents) === 0) {
            $this->io->success(
                sprintf('All %d time entries imported successfully!', count($successEvents))
            );
            return Command::SUCCESS;
        }

        // print partial success entries
        if (count($successEvents) > 0) {
            $this->io->warning(
                sprintf(
                    'Partially completed: %d of %d entries were imported successfully.',
                    count($successEvents),
                    count($finalTimeEntries),
                )
            );
        }

        return Command::FAILURE;
    }
}
