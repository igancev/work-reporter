<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination\YouTrack;

use LogicException;

final readonly class ProjectCollection
{
    /**
     * @param Project[] $projects
     */
    public function __construct(
        private array $projects,
    ) {
    }

    /**
     * @return Project[]
     */
    public function getProjects(): array
    {
        return $this->projects;
    }

    public function projectByTaskId(TaskId $taskId): Project
    {
        foreach ($this->projects as $project) {
            if ($project->shortName === $taskId->getProjectAlias()) {
                return $project;
            }
        }

        throw new LogicException(sprintf('Project with alias "%s" not found', $taskId->getProjectAlias()));
    }
}
