<?php

namespace Igancev\WorkReporter\Source\PlainJson;

use DateMalformedStringException;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\Source\SourceException;
use Igancev\WorkReporter\Source\TimeEntriesSource;
use Igancev\WorkReporter\TimeEntry;
use DateTimeImmutable;
use JsonException as NativeJsonException;

readonly class PlainJsonTimeEntriesSource implements TimeEntriesSource
{
    public function __construct(private string $jsonSourceFilePath)
    {
    }

    /**
     * @return TimeEntry[]
     * @throws SourceException
     */
    public function fetchTimeEntries(DateTimeImmutable $from, DateTimeImmutable $to): iterable
    {
        if (!file_exists($this->jsonSourceFilePath)) {
            throw new SourceException(
                sprintf('File not found: %s', $this->jsonSourceFilePath),
                ['path' => $this->jsonSourceFilePath],
            );
        }

        $content = @file_get_contents($this->jsonSourceFilePath);
        if ($content === false) {
            throw new SourceException(
                sprintf('Could not read file: %s', $this->jsonSourceFilePath),
                ['path' => $this->jsonSourceFilePath],
            );
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (NativeJsonException $e) {
            throw new SourceException(
                sprintf('Invalid JSON: %s', $e->getMessage()),
                ['path' => $this->jsonSourceFilePath],
                $e,
            );
        }

        if (!isset($data['timeEntries']) || !is_array($data['timeEntries'])) {
            throw new SourceException(
                'JSON must contain a "timeEntries" array',
                ['path' => $this->jsonSourceFilePath],
            );
        }

        $timeEntries = [];
        foreach ($data['timeEntries'] as $timeEntryRaw) {
            $dateString = $timeEntryRaw['date'] ?? throw new SourceException(
                'Date is required',
                ['path' => $this->jsonSourceFilePath],
            );

            try {
                $date = new DateTimeImmutable($dateString);
            } catch (DateMalformedStringException $e) {
                throw new SourceException(
                    sprintf('Invalid date format: %s', $dateString),
                    ['path' => $this->jsonSourceFilePath],
                    $e,
                );
            }

            if ($date < $from || $date > $to) {
                continue;
            }

            $timeEntries[] = new TimeEntry(
                $timeEntryRaw['taskId'] ?? throw new SourceException(
                    'TaskId is required',
                    ['path' => $this->jsonSourceFilePath],
                ),
                Duration::fromString($timeEntryRaw['duration'] ?? throw new SourceException(
                    'Duration is required',
                    ['path' => $this->jsonSourceFilePath],
                )),
                $timeEntryRaw['workType'] ?? throw new SourceException(
                    'WorkType is required',
                    ['path' => $this->jsonSourceFilePath],
                ),
                $date,
                $timeEntryRaw['comment'] ?? '',
            );
        }

        return $timeEntries;
    }
}
