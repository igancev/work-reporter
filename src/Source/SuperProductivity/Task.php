<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source\SuperProductivity;

use Igancev\WorkReporter\Duration;
use DateTimeImmutable;
use LogicException;
use RuntimeException;

/**
 * @internal
 */
final readonly class Task
{
    public function __construct(
        public string $id,
        public ?string $parentTitle,
        public string $title,
        /** @var Task[] List of subtasks */
        public array $subTasks,
        /** @var array<string, int> List of dates in format 'YYYY-MM-DD' => time in milliseconds */
        public array $timeSpentOnDay,
        /** @var Tag[] */
        public array $tags,
    ) {
        if (!empty($this->subTasks) && $this->isSubtask()) {
            throw new LogicException('Subtask cannot have subtasks');
        }
    }

    public function isSubtask(): bool
    {
        return $this->parentTitle !== null;
    }

    /**
     * @param string[] $days
     */
    public function hasSpentOnDays(array $days): bool
    {
        foreach ($days as $day) {
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $day);
            if ($dateTime === false) {
                throw new RuntimeException('Invalid date format');
            }

            if (array_key_exists($day, $this->timeSpentOnDay)) {
                return true;
            }
        }

        return false;
    }

    public function getDurationByDay(string $day): Duration
    {
        return Duration::fromMilliseconds($this->timeSpentOnDay[$day]);
    }

    public function hasDurationByDay(string $day): bool
    {
        return array_key_exists($day, $this->timeSpentOnDay);
    }

    public function getTaskId(): string
    {
        if ($this->isSubtask() && $this->parentTitle !== null) {
            return $this->parseTaskId($this->parentTitle);
        }

        return $this->parseTaskId($this->title);
    }

    public function workType(): string
    {
        foreach ($this->tags as $tag) {
            return $tag->name;
        }

        $workType = $this->parseWorkType($this->title);

        if (!$workType && $this->parentTitle) {
            $workType = $this->parseWorkType($this->parentTitle);
        }

        return $workType ?? 'Встречи';
    }

    private function parseTaskId(string $title): string
    {
        // regexp для поиска ID в любом месте заголовка
        $taskIdAnywherePattern = '/([A-Za-z0-9]+-\d+)/';

        if (preg_match($taskIdAnywherePattern, $title, $m, PREG_OFFSET_CAPTURE)) {
            return $m[1][0];
        }

        throw new RuntimeException('Unable to parse task id from title "' . $title . '"');
    }

    /**
     * Extracts work type from the title.
     * It looks for a word in square brackets first, and if not found, it takes the first word.
     *
     * Examples:
     * - "[Development] Task" -> "Development"
     * - "Meeting with team" -> "Meeting"
     */
    private function parseWorkType(string $title): ?string
    {
        $title = str_replace("\u{00A0}", ' ', $title);
        $title = trim($title);

        if ($title === '') {
            return null;
        }

        if (preg_match('/\[([^\]]+)\]/', $title, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^([^\s,.;:!?]+)/u', $title, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
