<?php

declare(strict_types=1);

namespace Tests\Config;

use Igancev\WorkReporter\Config\Config;
use Igancev\WorkReporter\Config\DestinationConfig\DestinationsConfig;
use Igancev\WorkReporter\Config\DestinationConfig\YouTrackConfig;
use Igancev\WorkReporter\Config\SourceConfig\PlainJsonConfig;
use Igancev\WorkReporter\Config\SourceConfig\SourcesConfig;
use Igancev\WorkReporter\Config\SourceConfig\SuperProductivityConfig;
use Igancev\WorkReporter\Destination\DestinationType;
use Igancev\WorkReporter\Source\SourceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
#[CoversClass(SourcesConfig::class)]
#[CoversClass(DestinationsConfig::class)]
#[CoversClass(PlainJsonConfig::class)]
#[CoversClass(SuperProductivityConfig::class)]
#[CoversClass(YouTrackConfig::class)]
final class ConfigTest extends TestCase
{
    public function testConfigStoresAllProperties(): void
    {
        $sourcesConfig = new SourcesConfig(
            new SuperProductivityConfig('/tmp/sync'),
            new PlainJsonConfig('/tmp/plain.json'),
        );
        $destinationsConfig = new DestinationsConfig(
            new YouTrackConfig('http://yt.local', 'token-abc'),
        );

        $config = new Config(
            SourceType::SuperProductivity,
            DestinationType::YouTrack,
            $sourcesConfig,
            $destinationsConfig,
        );

        $this->assertSame(SourceType::SuperProductivity, $config->source);
        $this->assertSame(DestinationType::YouTrack, $config->destination);
        $this->assertSame($sourcesConfig, $config->sources);
        $this->assertSame($destinationsConfig, $config->destinations);
    }

    public function testSourcesConfigDefaults(): void
    {
        $config = new SourcesConfig();

        $this->assertNull($config->superProductivity);
        $this->assertNull($config->plainJson);
    }

    public function testDestinationsConfigDefaults(): void
    {
        $config = new DestinationsConfig();

        $this->assertNull($config->youTrack);
    }

    public function testPlainJsonConfigStoresFilePath(): void
    {
        $config = new PlainJsonConfig('/path/to/file.json');

        $this->assertSame('/path/to/file.json', $config->filePath);
    }

    public function testSuperProductivityConfigStoresSyncFilePath(): void
    {
        $config = new SuperProductivityConfig('/path/to/sync');

        $this->assertSame('/path/to/sync', $config->syncFilePath);
    }

    public function testYouTrackConfigStoresUrlAndToken(): void
    {
        $config = new YouTrackConfig('http://yt.local', 'my-token');

        $this->assertSame('http://yt.local', $config->url);
        $this->assertSame('my-token', $config->token);
    }
}
