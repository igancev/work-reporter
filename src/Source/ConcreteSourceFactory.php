<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source;

use Igancev\WorkReporter\Config\ConfigProvider;
use Igancev\WorkReporter\Source\PlainJson\PlainJsonTimeEntriesSource;
use Igancev\WorkReporter\Source\SuperProductivity\SuperProductivitySyncSource;

final readonly class ConcreteSourceFactory implements TimeEntriesSourceFactory
{
    public function __construct(
        private ConfigProvider $configProvider,
    ) {
    }

    public function build(SourceType $source): TimeEntriesSource
    {
        return match ($source) {
            SourceType::PlainJson => $this->buildPlainJsonSource(),
            SourceType::SuperProductivity => $this->buildFromSuperProductivitySource(),
        };
    }

    /**
     * @throws SourceException
     */
    private function buildPlainJsonSource(): PlainJsonTimeEntriesSource
    {
        $config = $this->configProvider->getConfig()->sources->plainJson;
        if ($config === null) {
            throw new SourceException("PlainJson source configuration is missing");
        }

        return new PlainJsonTimeEntriesSource($config->filePath);
    }

    /**
     * @throws SourceException
     */
    private function buildFromSuperProductivitySource(): SuperProductivitySyncSource
    {
        $config = $this->configProvider->getConfig()->sources->superProductivity;
        if ($config === null) {
            throw new SourceException("SuperProductivity source configuration is missing");
        }

        return new SuperProductivitySyncSource($config->syncFilePath);
    }
}
