<?php

namespace Igancev\WorkReporter\Config\DestinationConfig;

use Igancev\WorkReporter\Config\ConfigException;

readonly class DestinationsConfig
{
    public function __construct(
        private ?YouTrackConfig $youTrack = null,
    ) {
    }

    public function getYouTrack(): YouTrackConfig
    {
        return $this->youTrack ?? throw new ConfigException(
            'Destination youTrack is not configured'
        );
    }
}
