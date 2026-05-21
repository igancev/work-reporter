<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use Igancev\WorkReporter\Config\Config;
use Igancev\WorkReporter\Config\ConfigProvider;
use Igancev\WorkReporter\Config\DestinationConfig\DestinationsConfig;
use Igancev\WorkReporter\Config\DestinationConfig\YouTrackConfig;
use Igancev\WorkReporter\Config\SourceConfig\PlainJsonConfig;
use Igancev\WorkReporter\Config\SourceConfig\SourcesConfig;
use Igancev\WorkReporter\Config\SourceConfig\SuperProductivityConfig;
use Igancev\WorkReporter\Destination\DestinationType;
use Igancev\WorkReporter\Source\ConcreteSourceFactory;
use Igancev\WorkReporter\Source\PlainJson\PlainJsonTimeEntriesSource;
use Igancev\WorkReporter\Source\SourceType;
use Igancev\WorkReporter\Source\SuperProductivity\SuperProductivitySyncSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConcreteSourceFactory::class)]
class ConcreteSourceFactoryTest extends TestCase
{
    private ConfigProvider&Stub $configProvider;
    private ConcreteSourceFactory $factory;

    protected function setUp(): void
    {
        $this->configProvider = $this->createStub(ConfigProvider::class);
        $this->factory = new ConcreteSourceFactory($this->configProvider);
    }

    public function testBuildPlainJsonSourceSuccessfully(): void
    {
        // Arrange
        $plainJsonConfig = new PlainJsonConfig(filePath: 'path/to/file.json');
        $sourcesConfig = new SourcesConfig(plainJson: $plainJsonConfig);
        $config = $this->createConfig(SourceType::PlainJson, $sourcesConfig);

        $this->configProvider->method('getConfig')->willReturn($config);

        // Act
        $source = $this->factory->build(SourceType::PlainJson);

        // Assert
        $this->assertInstanceOf(PlainJsonTimeEntriesSource::class, $source);
    }

    public function testBuildSuperProductivitySourceSuccessfully(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'sp_sync');
        $content = 'pf_4.4__{"mainModelData":{"task":{"ids":[],"entities":{}},'
            . '"archiveYoung":{"task":{"ids":[],"entities":{}}},'
            . '"archiveOld":{"task":{"ids":[],"entities":{}}},'
            . '"tag":{"ids":[],"entities":{}}}}';
        file_put_contents($tempFile, $content);

        try {
            $superProductivityConfig = new SuperProductivityConfig(syncFilePath: $tempFile);
            $sourcesConfig = new SourcesConfig(superProductivity: $superProductivityConfig);
            $config = $this->createConfig(SourceType::SuperProductivity, $sourcesConfig);

            $this->configProvider->method('getConfig')->willReturn($config);

            // Act
            $source = $this->factory->build(SourceType::SuperProductivity);

            // Assert
            $this->assertInstanceOf(SuperProductivitySyncSource::class, $source);
        } finally {
            unlink($tempFile);
        }
    }

    private function createConfig(SourceType $sourceType, SourcesConfig $sources): Config
    {
        return new Config(
            source: $sourceType,
            destination: DestinationType::YouTrack,
            sources: $sources,
            destinations: new DestinationsConfig(youTrack: new YouTrackConfig('http://yt.local', 'token-abc')),
        );
    }
}
