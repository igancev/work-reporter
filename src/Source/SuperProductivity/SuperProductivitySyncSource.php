<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source\SuperProductivity;

use Igancev\WorkReporter\Source\SourceException;
use Igancev\WorkReporter\Source\TimeEntriesSource;
use Igancev\WorkReporter\TimeEntry;
use DateTimeImmutable;

final readonly class SuperProductivitySyncSource implements TimeEntriesSource
{
    private Storage $storage;
    private TaskFactory $taskFactory;

    /**
     * @throws SourceException
     */
    public function __construct(
        string $syncMetaPath,
    ) {
        $this->storage = new Storage($syncMetaPath);
        $this->taskFactory = new TaskFactory($this->storage);
    }

    public function fetchTimeEntries(DateTimeImmutable $from, DateTimeImmutable $to): iterable
    {
        $targetDates = $this->listOfDaysInPeriod($from, $to);

        $timeEntries = [];
        foreach ($this->storage->getTaskIds() as $taskId) {
            $task = $this->taskFactory->fromTaskId($taskId);

            if ($task->isSubtask() || !$task->hasSpentOnDays($targetDates)) {
                continue;
            }

            foreach ($targetDates as $day) {
                if ($task->hasDurationByDay($day) && empty($task->subTasks)) {
                    $timeEntries[] = new TimeEntry(
                        taskId: $task->getTaskId(),
                        duration: $task->getDurationByDay($day),
                        workType: $task->workType(),
                        date: new DateTimeImmutable($day),
                        comment: '',
                    );
                }

                foreach ($task->subTasks as $subTask) {
                    if ($subTask->hasDurationByDay($day)) {
                        $timeEntries[] = new TimeEntry(
                            taskId: $subTask->getTaskId(),
                            duration: $subTask->getDurationByDay($day),
                            workType: $subTask->workType(),
                            date: new DateTimeImmutable($day),
                            comment: $subTask->title,
                        );
                    }
                }
            }
        }

        return $timeEntries;
    }

    /**
     * @return list<string> Date in format 'Y-m-d'
     */
    private function listOfDaysInPeriod(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): array
    {
        $from = $dateFrom->setTime(0, 0);
        $to = $dateTo->setTime(0, 0);

        if ($from > $to) {
            return [];
        }

        $period = new \DatePeriod(
            $from,
            new \DateInterval('P1D'),
            $to->modify('+1 day'),
        );

        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        return $dates;
    }
}
