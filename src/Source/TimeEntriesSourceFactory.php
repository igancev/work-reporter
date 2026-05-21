<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source;

interface TimeEntriesSourceFactory
{
    /**
     * @throws SourceException
     */
    public function build(SourceType $source): TimeEntriesSource;
}
