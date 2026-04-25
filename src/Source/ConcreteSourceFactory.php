<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source;

use Igancev\WorkReporter\Source\PlainJson\PlainJsonTimeEntriesSource;
use Igancev\WorkReporter\Source\SuperProductivity\SuperProductivitySyncSource;

final readonly class ConcreteSourceFactory implements TimeEntriesSourceFactory
{
    public function build(SourceType $source): TimeEntriesSource
    {
        return match ($source) {
            SourceType::PlainJson => $this->buildPlainJsonSource(),
            SourceType::SuperProductivity => $this->buildFromSuperProductivitySource(),
        };
    }

    private function buildPlainJsonSource(): PlainJsonTimeEntriesSource
    {
        // todo: load from config
        return new PlainJsonTimeEntriesSource('../jsonList.json');
    }

    private function buildFromSuperProductivitySource(): SuperProductivitySyncSource
    {
        // todo: load from config
        $superProductivitySyncFile = '~/.config/superProductivity/backups/sync/__meta_';

        return new SuperProductivitySyncSource($superProductivitySyncFile);
    }
}
