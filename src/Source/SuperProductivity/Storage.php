<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source\SuperProductivity;

use Igancev\WorkReporter\Source\SourceException;
use JsonException;

/**
 * @internal
 */
readonly class Storage
{
    private string $syncMetaPath;
    /** @var array<mixed> */
    private array $jsonData;

    /**
     * @throws SourceException
     */
    public function __construct(string $syncMetaPath)
    {
        $this->syncMetaPath = $syncMetaPath;
        $this->jsonData = $this->parseJson();
    }

    /** @return iterable<string> */
    public function getTaskIds(): iterable
    {
        // active tasks?
        foreach ($this->jsonData['mainModelData']['task']['ids'] as $taskId) {
            yield $taskId;
        }
        // archive young
        foreach ($this->jsonData['mainModelData']['archiveYoung']['task']['ids'] as $taskId) {
            yield $taskId;
        }
        // archive old
        foreach ($this->jsonData['mainModelData']['archiveOld']['task']['ids'] as $taskId) {
            yield $taskId;
        }
    }

    /**
     * @return array{
     *     id: string,
     *     parentId: string|null,
     *     title: string,
     *     timeSpentOnDay: array<string, int>,
     *     subTaskIds: string[],
     *     tagIds: string[],
     * }
     * @throws SourceException
     */
    public function getTaskById(string $taskId): array
    {
        if (array_key_exists($taskId, $this->jsonData['mainModelData']['task']['entities'])) {
            return $this->jsonData['mainModelData']['task']['entities'][$taskId];
        }
        if (array_key_exists($taskId, $this->jsonData['mainModelData']['archiveYoung']['task']['entities'])) {
            return $this->jsonData['mainModelData']['archiveYoung']['task']['entities'][$taskId];
        }
        if (array_key_exists($taskId, $this->jsonData['mainModelData']['archiveOld']['task']['entities'])) {
            return $this->jsonData['mainModelData']['archiveOld']['task']['entities'][$taskId];
        }

        throw new SourceException('SuperProductivitySyncDataSource: Unable to find task with id ' . $taskId);
    }

    /**
     * @return array<mixed>
     * @throws SourceException
     */
    private function parseJson(): array
    {
        $content = @file_get_contents($this->syncMetaPath);
        if ($content === false) {
            throw new SourceException(
                'SuperProductivitySyncDataSource: Unable to read sync data file: ' . $this->syncMetaPath,
            );
        }

        // specific format of SuperProductivity sync data
        // json file starts with prefix like `pf_4.4__`, example: pf_4.4__{"revMap":{"menuTree":"1770462116462", ...
        $startPos = strpos($content, '{');
        if ($startPos === false) {
            throw new SourceException(
                'SuperProductivitySyncDataSource: Unable to parse start position "{" ' . $this->syncMetaPath,
            );
        }

        $jsonString = substr($content, $startPos);

        try {
            $jsonData = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SourceException('SuperProductivitySyncDataSource: Unable to parse JSON: ' . $e->getMessage());
        }

        return $jsonData;
    }

    /**
     * @throws SourceException
     */
    public function getTagById(string $tagId): Tag
    {
        if (!array_key_exists($tagId, $this->jsonData['mainModelData']['tag']['entities'])) {
            throw new SourceException('SuperProductivitySyncDataSource: Unable to find tag with id ' . $tagId);
        }

        $tagName = $this->jsonData['mainModelData']['tag']['entities'][$tagId]['title'];

        return new Tag($tagId, $tagName);
    }
}
