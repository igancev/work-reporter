<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination\YouTrack;

final readonly class Project
{
    public function __construct(
        public string $id,
        public string $name,
        public string $shortName,
    ) {
    }
}
