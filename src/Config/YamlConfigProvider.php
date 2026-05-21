<?php

namespace Igancev\WorkReporter\Config;

use Igancev\WorkReporter\Config\DestinationConfig\DestinationsConfig;
use Igancev\WorkReporter\Config\DestinationConfig\YouTrackConfig;
use Igancev\WorkReporter\Config\SourceConfig\PlainJsonConfig;
use Igancev\WorkReporter\Config\SourceConfig\SourcesConfig;
use Igancev\WorkReporter\Config\SourceConfig\SuperProductivityConfig;
use Igancev\WorkReporter\Destination\DestinationType;
use Igancev\WorkReporter\Source\SourceType;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use ValueError;

class YamlConfigProvider implements ConfigProvider
{
    private ?Config $config = null;

    public function __construct(
        private readonly string $configPath = "~/.config/work-reporter/config.yaml"
    ) {
    }

    public function getConfig(): Config
    {
        if ($this->config !== null) {
            return $this->config;
        }

        $path = str_replace("~", (string)getenv("HOME"), $this->configPath);

        if (!file_exists($path)) {
            throw new ConfigException(sprintf(
                "Configuration file not found at: %s\n\n"
                . "- You can generate a default configuration file by running: `work-reporter init`\n"
                . "- Or specify a custom config path using the `-c` flag: `work-reporter -c /path/to/config.yaml`",
                $path,
            ));
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            throw new ConfigException(sprintf("Invalid YAML syntax in config file: %s", $e->getMessage()), 0, $e);
        }

        if (!is_array($data)) {
            throw new ConfigException("Configuration file is empty or has invalid structure.");
        }

        $this->config = new Config(
            $this->parseSourceType($data),
            $this->parseDestinationType($data),
            $this->parseSourcesConfig($data),
            $this->parseDestinationsConfig($data),
        );

        return $this->config;
    }

    /** @param array<string, mixed> $data */
    private function parseSourceType(array $data): SourceType
    {
        if (!isset($data["source"])) {
            throw new ConfigException('Missing required config key: "source".');
        }

        if (!is_string($data["source"])) {
            throw new ConfigException(sprintf(
                'Config key "source" must be a string, got %s.',
                get_debug_type($data["source"]),
            ));
        }

        try {
            return SourceType::from($data["source"]);
        } catch (ValueError) {
            throw new ConfigException(sprintf(
                'Invalid source type: "%s". Allowed values: %s.',
                $data["source"],
                implode(', ', array_map(fn(SourceType $t) => $t->value, SourceType::cases())),
            ));
        }
    }

    /** @param array<string, mixed> $data */
    private function parseDestinationType(array $data): DestinationType
    {
        if (!isset($data["destination"])) {
            throw new ConfigException('Missing required config key: "destination".');
        }

        if (!is_string($data["destination"])) {
            throw new ConfigException(sprintf(
                'Config key "destination" must be a string, got %s.',
                get_debug_type($data["destination"]),
            ));
        }

        try {
            return DestinationType::from($data["destination"]);
        } catch (ValueError) {
            throw new ConfigException(sprintf(
                'Invalid destination type: "%s". Allowed values: %s.',
                $data["destination"],
                implode(', ', array_map(fn(DestinationType $t) => $t->value, DestinationType::cases())),
            ));
        }
    }

    /** @param array<string, mixed> $data */
    private function parseSourcesConfig(array $data): SourcesConfig
    {
        $sources = $data["sources"] ?? [];
        if (!is_array($sources)) {
            throw new ConfigException(sprintf(
                'Config key "sources" must be a mapping, got %s.',
                get_debug_type($sources),
            ));
        }

        return new SourcesConfig(
            isset($sources["superProductivity"])
                ? $this->parseSuperProductivityConfig($sources["superProductivity"])
                : null,
            isset($sources["plainJson"])
                ? $this->parsePlainJsonConfig($sources["plainJson"])
                : null,
        );
    }

    private function parseSuperProductivityConfig(mixed $data): SuperProductivityConfig
    {
        if (!is_array($data) || !isset($data["syncFilePath"])) {
            throw new ConfigException('Missing required key "syncFilePath" in sources.superProductivity config.');
        }

        return new SuperProductivityConfig($data["syncFilePath"]);
    }

    private function parsePlainJsonConfig(mixed $data): PlainJsonConfig
    {
        if (!is_array($data) || !isset($data["filePath"])) {
            throw new ConfigException('Missing required key "filePath" in sources.plainJson config.');
        }

        return new PlainJsonConfig($data["filePath"]);
    }

    /** @param array<string, mixed> $data */
    private function parseDestinationsConfig(array $data): DestinationsConfig
    {
        $destinations = $data["destinations"] ?? [];
        if (!is_array($destinations)) {
            throw new ConfigException(sprintf(
                'Config key "destinations" must be a mapping, got %s.',
                get_debug_type($destinations),
            ));
        }

        return new DestinationsConfig(
            isset($destinations["youTrack"])
                ? $this->parseYouTrackConfig($destinations["youTrack"])
                : null,
        );
    }

    private function parseYouTrackConfig(mixed $data): YouTrackConfig
    {
        if (!is_array($data)) {
            throw new ConfigException('Invalid youTrack destination config: expected a mapping.');
        }

        if (!isset($data["url"])) {
            throw new ConfigException('Missing required key "url" in destinations.youTrack config.');
        }

        if (!isset($data["token"])) {
            throw new ConfigException('Missing required key "token" in destinations.youTrack config.');
        }

        return new YouTrackConfig($data["url"], $data["token"]);
    }
}
