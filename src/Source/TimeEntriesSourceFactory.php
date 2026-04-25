<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source;

interface TimeEntriesSourceFactory
{
    public function build(SourceType $source): TimeEntriesSource;
}
