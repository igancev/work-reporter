<?php

namespace Igancev\WorkReporter\Config\SourceConfig;

use Igancev\WorkReporter\Config\ConfigException;

readonly class SourcesConfig
{
    public function __construct(
        private ?SuperProductivityConfig $superProductivity = null,
        private ?PlainJsonConfig $plainJson = null,
    ) {
    }

    public function getPlainJson(): PlainJsonConfig
    {
        return $this->plainJson ?? throw new ConfigException(
            'Source plainJson is not configured'
        );
    }

    public function getSuperProductivity(): SuperProductivityConfig
    {
        return $this->superProductivity ?? throw new ConfigException(
            'Source superProductivity is not configured'
        );
    }
}
