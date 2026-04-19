<?php

declare(strict_types=1);

namespace Igancev\WorkReporter;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TimeEntryCollection
{
    /**
     * @var TimeEntry[]
     */
    private array $items;

    /**
     * @param iterable<TimeEntry> $items
     */
    public function __construct(iterable $items)
    {
        $this->items = is_array($items) ? $items : iterator_to_array($items);
    }

    /**
     * @return TimeEntry[]
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * @return TimeEntry[]
     */
    public function grouped(): array
    {
        $groupedData = [];
        foreach ($this->items as $item) {
            $date = $item->date->format('Y-m-d');
            $taskId = $item->taskId;
            $workType = $item->workType;

            $groupedData[$date][$taskId][$workType]['duration'] =
                ($groupedData[$date][$taskId][$workType]['duration'] ?? 0) + $item->duration->toMilliseconds();

            if ($item->comment !== '') {
                $groupedData[$date][$taskId][$workType]['comments'][] = trim($item->comment);
            }
        }

        ksort($groupedData);

        $finalEntries = [];
        foreach ($groupedData as $date => $tasks) {
            foreach ($tasks as $taskId => $workTypes) {
                foreach ($workTypes as $workType => $data) {
                    $finalEntries[] = new TimeEntry(
                        taskId: $taskId,
                        duration: Duration::fromMilliseconds($data['duration']),
                        workType: $workType,
                        date: DateTimeImmutable::createFromFormat('Y-m-d', $date)
                            ?: throw new InvalidArgumentException("Invalid date format for $date"),
                        comment: $this->combineComments($data['comments'] ?? []),
                    );
                }
            }
        }

        return $finalEntries;
    }

    /**
     * @param string[] $comments
     */
    private function combineComments(array $comments): string
    {
        if (empty($comments)) {
            return '';
        }

        $uniqueComments = array_unique($comments);
        $formattedComments = array_map(static fn($c) => "- $c", $uniqueComments);

        return implode("\n", $formattedComments);
    }
}
