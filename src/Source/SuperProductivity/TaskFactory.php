<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source\SuperProductivity;

/**
 * @internal
 */
final readonly class TaskFactory
{
    public function __construct(
        private Storage $storage,
    ) {
    }

    public function fromTaskId(string $taskId): Task
    {
        $rawTask = $this->storage->getTaskById($taskId);
        $tagIds = $rawTask['tagIds'];
        $tags = [];
        foreach ($tagIds as $tagId) {
            $tags[] = $this->storage->getTagById($tagId);
        }

        $parentTaskId = $rawTask['parentId'] ?? null;
        $parentTaskTitle = null;
        if ($parentTaskId) {
            $parentTaskTitle = $this->storage->getTaskById($parentTaskId)['title'];
        }

        $parentId = $rawTask['parentId'] ?? null;
        $subtasks = [];
        if (!$parentId) {
            $subtaskIds = $rawTask['subTaskIds'];
            foreach ($subtaskIds as $subId) {
                $subtasks[] = $this->fromTaskId($subId);
            }
        }

        return new Task(
            id: $rawTask['id'],
            parentTitle: $parentTaskTitle,
            title: $rawTask['title'],
            subTasks: $subtasks,
            timeSpentOnDay: $rawTask['timeSpentOnDay'],
            tags: $tags,
        );
    }
}
