<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source\SuperProductivity;

use Igancev\WorkReporter\Platform\HomeDirectory;
use Igancev\WorkReporter\Source\SourceException;
use JsonException;

/**
 * @internal
 */
readonly class Storage
{
    private const string ERROR_PREFIX = 'SuperProductivitySyncDataSource: ';
    private const array REQUIRED_TASK_KEYS = ['id', 'title', 'timeSpentOnDay', 'tagIds', 'subTaskIds'];

    private string $syncFilePath;
    /** @var array<mixed> */
    private array $root;
    /** @var list<array<mixed>> Active tasks first, then archiveYoung, then archiveOld */
    private array $sections;

    /**
     * @throws SourceException
     */
    public function __construct(string $syncFilePath)
    {
        $this->syncFilePath = str_replace('~', HomeDirectory::resolve(), $syncFilePath);
        $data = $this->parseJson();
        $this->root = $this->resolveRoot($data);
        $this->sections = [
            $this->root,
            $this->resolveArchive($data, $this->root, 'archiveYoung'),
            $this->resolveArchive($data, $this->root, 'archiveOld'),
        ];
    }

    /** @return iterable<string> */
    public function getTaskIds(): iterable
    {
        foreach ($this->sections as $section) {
            // plain `yield` instead of `yield from`: keys of the id lists overlap between sections
            foreach ($section['task']['ids'] as $taskId) {
                yield $taskId;
            }
        }
    }

    /**
     * @return array{
     *     id: string,
     *     parentId?: string|null,
     *     title: string,
     *     timeSpentOnDay: array<string, int>,
     *     subTaskIds: string[],
     *     tagIds: string[],
     * }
     * @throws SourceException
     */
    public function getTaskById(string $taskId): array
    {
        // a task (or its parent / subtask) may live in the active list or in one of the archives
        foreach ($this->sections as $section) {
            if (array_key_exists($taskId, $section['task']['entities'])) {
                $rawTask = $section['task']['entities'][$taskId];
                $this->assertTask($rawTask, $taskId);

                return $rawTask;
            }
        }

        throw new SourceException(self::ERROR_PREFIX . 'Unable to find task with id ' . $taskId);
    }

    /**
     * @throws SourceException
     */
    public function getTagById(string $tagId): Tag
    {
        // tags exist only in the root section in both sync file formats
        if (!array_key_exists($tagId, $this->root['tag']['entities'])) {
            throw new SourceException(self::ERROR_PREFIX . 'Unable to find tag with id ' . $tagId);
        }

        $tagName = $this->root['tag']['entities'][$tagId]['title'] ?? null;
        if ($tagName === null) {
            throw new SourceException(
                self::ERROR_PREFIX . 'Tag with id ' . $tagId . ' is missing required key "title"',
            );
        }

        return new Tag($tagId, $tagName);
    }

    /**
     * @return array<mixed>
     * @throws SourceException
     */
    private function parseJson(): array
    {
        $content = @file_get_contents($this->syncFilePath);
        if ($content === false) {
            throw new SourceException(
                self::ERROR_PREFIX . 'Unable to read sync data file: ' . $this->syncFilePath,
            );
        }

        // specific format of SuperProductivity sync data
        // json file starts with prefix like `pf_4.4__`, example: pf_4.4__{"revMap":{"menuTree":"1770462116462", ...
        $startPos = strpos($content, '{');
        if ($startPos === false) {
            throw new SourceException(
                self::ERROR_PREFIX . 'Unable to parse start position "{" ' . $this->syncFilePath,
            );
        }

        $jsonString = substr($content, $startPos);

        try {
            $jsonData = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SourceException(self::ERROR_PREFIX . 'Unable to parse JSON: ' . $e->getMessage());
        }

        return $jsonData;
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     * @throws SourceException
     */
    private function resolveRoot(array $data): array
    {
        // old format nests everything under "mainModelData", new format uses "state"
        $root = $data['mainModelData'] ?? $data['state'] ?? null;

        if (!is_array($root)) {
            throw new SourceException(
                self::ERROR_PREFIX . 'Unable to find "mainModelData" (sync format v1) or "state"'
                . ' (sync format v2) section in sync data file: ' . $this->syncFilePath,
                ['path' => $this->syncFilePath, 'topLevelKeys' => array_keys($data)],
            );
        }

        $this->assertTaskSection($root, 'task');

        if (!isset($root['tag']['entities']) || !is_array($root['tag']['entities'])) {
            throw new SourceException(
                self::ERROR_PREFIX . 'Unable to find "tag.entities" in sync data file: '
                . $this->syncFilePath,
            );
        }

        return $root;
    }

    /**
     * @param array<mixed> $data
     * @param array<mixed> $root
     * @return array<mixed>
     * @throws SourceException
     */
    private function resolveArchive(array $data, array $root, string $archiveKey): array
    {
        // the new format stores archives at the top level, the old format nests them under the root;
        // the top level MUST win: the new format also keeps empty "state.archive*" remnants
        $archive = $data[$archiveKey] ?? $root[$archiveKey] ?? null;

        if (!is_array($archive)) {
            throw new SourceException(
                self::ERROR_PREFIX . 'Unable to find "' . $archiveKey . '" section in sync data file: '
                . $this->syncFilePath,
            );
        }

        $this->assertTaskSection($archive, $archiveKey . '.task');

        return $archive;
    }

    /**
     * @param array<mixed> $section
     * @throws SourceException
     */
    private function assertTaskSection(array $section, string $sectionName): void
    {
        foreach (['ids', 'entities'] as $key) {
            if (!isset($section['task'][$key]) || !is_array($section['task'][$key])) {
                throw new SourceException(
                    self::ERROR_PREFIX . 'Unable to find "' . $sectionName . '.' . $key
                    . '" in sync data file: ' . $this->syncFilePath,
                );
            }
        }
    }

    /**
     * @throws SourceException
     */
    private function assertTask(mixed $rawTask, string $taskId): void
    {
        if (!is_array($rawTask)) {
            throw new SourceException(
                self::ERROR_PREFIX . 'Task with id ' . $taskId . ' must be an object',
            );
        }

        foreach (self::REQUIRED_TASK_KEYS as $key) {
            if (!array_key_exists($key, $rawTask)) {
                throw new SourceException(
                    self::ERROR_PREFIX . 'Task with id ' . $taskId . ' is missing required key "'
                    . $key . '"',
                );
            }
        }
    }
}
