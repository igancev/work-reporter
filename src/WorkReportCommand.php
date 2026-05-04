<?php

namespace Igancev\WorkReporter;

use DateTimeImmutable;
use Igancev\WorkReporter\Config\ConfigProvider;
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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeEntriesSource = $this->timeEntriesSourceFactory->build($this->configProvider->get()->source);
        $this->destination = $this->destinationFactory->build($this->configProvider->get()->destination);

        try {
            $timeEntries = $timeEntriesSource->fetchTimeEntries($this->from, $this->to);
        } catch (SourceException $e) {
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        if (empty($timeEntries)) {
            $this->io->warning('No time entries found in the source.');
            return Command::SUCCESS;
        }

        $timeEntries = $this->filterByMinDuration((array) $timeEntries);

        if (empty($timeEntries)) {
            $this->io->warning('No time entries remain after filtering by minimum duration.');
            return Command::SUCCESS;
        }

        $timeEntries = new TimeEntryCollection($timeEntries);
        $finalTimeEntries = $this->isGrouped ? $timeEntries->grouped() : $timeEntries->all();

        $this->displayTimeEntries($finalTimeEntries);

        if (!$this->io->confirm('Do you want to send these time entries to YouTrack?', false)) {
            $this->io->note('Sending cancelled.');
            return Command::SUCCESS;
        }

        return $this->sendToDestination($finalTimeEntries);
    }

    private function parseDate(string $dateStr): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if (!$date || $date->format('Y-m-d') !== $dateStr) {
            throw new InvalidArgumentException(sprintf('Invalid date format: "%s". Expected YYYY-MM-DD.', $dateStr));
        }

        return $date->setTime(0, 0);
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
                    $entry->date->format('Y-m-d'),
                    $entry->comment,
                ];
                $totalDuration = $totalDuration->add($entry->duration);
            }

            $this->io->table(
                ['Task ID', 'Duration', 'Work Type', 'Date', 'Comment'],
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
        $this->io->title('Sending Time Entries');
        $this->io->text(sprintf('Sending %d time entries...', count($finalTimeEntries)));

        try {
            $deliveryResult = $this->destination->logTimeEntries($finalTimeEntries);
        } catch (DestinationException $e) {
            $this->io->section('Delivery Failed');
            $this->io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($deliveryResult->isSuccessful()) {
            $this->io->success(
                sprintf('All %d time entries imported successfully!', $deliveryResult->successfulCount())
            );
            return Command::SUCCESS;
        }

        // print partial success entries
        if ($deliveryResult->successfulCount() > 0) {
            $this->io->section('Partial Success');
            $this->io->note(sprintf('Successfully imported %d entries.', $deliveryResult->successfulCount()));

            $successfulData = [];
            foreach ($deliveryResult->successDelivered() as $success) {
                $successfulData[] = [
                    $success->taskId,
                    $success->date->format('Y-m-d'),
                    $success->duration->toString(),
                ];
            }
            $this->io->table(['Task ID', 'Date', 'Duration'], $successfulData);
        }

        // print failures
        $this->io->section('Failures');
        $this->io->error(sprintf('Failed to import %d entries:', $deliveryResult->failuresCount()));

        $failureData = [];
        foreach ($deliveryResult->failures() as $failure) {
            $failureData[] = [
                $failure->timeEntry->taskId,
                $failure->timeEntry->date->format('Y-m-d'),
                $failure->timeEntry->duration->toString(),
                $failure->exception->getMessage(),
            ];
        }
        $this->io->table(['Task ID', 'Date', 'Duration', 'Error'], $failureData);

        return Command::FAILURE;
    }

    private function parseMinDuration(string $value): Duration
    {
        if ($value === '') {
            return Duration::fromString('1m');
        }

        return Duration::fromString($value);
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
}
