<?php

namespace Igancev\WorkReporter\Config;

use Igancev\WorkReporter\Config\DestinationConfig\DestinationsConfig;
use Igancev\WorkReporter\Config\SourceConfig\SourcesConfig;
use Igancev\WorkReporter\Source\SourceType;
use Igancev\WorkReporter\Destination\DestinationType;
use LogicException;

readonly class Config
{
    public function __construct(
        public SourceType $source,
        public DestinationType $destination,
        public SourcesConfig $sources,
        public DestinationsConfig $destinations,
    ) {
        // @phpstan-ignore function.alreadyNarrowedType
        if (!property_exists($this->destinations, $this->destination->value)) {
            throw new LogicException("Destination {$this->destination->value} is not supported");
        }

        if (!property_exists($this->sources, $this->source->value)) {
            throw new LogicException("Source {$this->source->value} is not supported");
        }

        if ($this->destinations->{$this->destination->value} === null) {
            throw new ConfigException("Destination {$this->destination->value} is not configured");
        }

        if ($this->sources->{$this->source->value} === null) {
            throw new ConfigException("Source {$this->source->value} is not configured");
        }
    }
}
