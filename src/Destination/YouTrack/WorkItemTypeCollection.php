<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination\YouTrack;

use LogicException;

final class WorkItemTypeCollection
{
    /** @var array<string, WorkItemType[]> */
    private array $workItemTypesByProject;

    /**
     * @param WorkItemType[] $workItemTypes
     */
    public function __construct(
        array $workItemTypes,
    ) {
        foreach ($workItemTypes as $workItemType) {
            $this->workItemTypesByProject[$workItemType->projectId][] = $workItemType;
        }
    }

    public function getByProjectAndName(Project $project, string $workItemTypeName): WorkItemType
    {
        $projectId = $project->id;

        if (!array_key_exists($projectId, $this->workItemTypesByProject)) {
            throw new LogicException("Project with id '{$projectId}' not found");
        }

        foreach ($this->workItemTypesByProject[$projectId] as $key => $workItemType) {
            if (mb_strtolower($workItemType->name) === mb_strtolower($workItemTypeName)) {
                return $this->workItemTypesByProject[$projectId][$key];
            }
        }

        throw new LogicException(
            sprintf('WorkItemType "%s" not found in project "%s"', $workItemTypeName, $project->name)
        );
    }
}
