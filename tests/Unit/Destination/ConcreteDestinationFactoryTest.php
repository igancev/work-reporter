<?php

declare(strict_types=1);

namespace Tests\Unit\Destination;

use Igancev\WorkReporter\Config\Config;
use Igancev\WorkReporter\Config\ConfigProvider;
use Igancev\WorkReporter\Config\DestinationConfig\DestinationsConfig;
use Igancev\WorkReporter\Config\DestinationConfig\YouTrackConfig;
use Igancev\WorkReporter\Config\SourceConfig\SourcesConfig;
use Igancev\WorkReporter\Destination\ConcreteDestinationFactory;
use Igancev\WorkReporter\Destination\DestinationType;
use Igancev\WorkReporter\Destination\YouTrack\YouTrackDestination;
use Igancev\WorkReporter\Source\SourceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ConcreteDestinationFactory::class)]
class ConcreteDestinationFactoryTest extends TestCase
{
    private ConfigProvider&Stub $configProvider;
    private ConcreteDestinationFactory $factory;

    protected function setUp(): void
    {
        $this->configProvider = $this->createStub(ConfigProvider::class);
        $this->factory = new ConcreteDestinationFactory($this->configProvider);
    }

    public function testBuildYouTrackDestinationSuccessfully(): void
    {
        // Arrange
        $youTrackConfig = new YouTrackConfig(url: 'https://example.youtrack.cloud', token: 'perm:token');
        $destinationsConfig = new DestinationsConfig(youTrack: $youTrackConfig);
        $config = $this->createConfig($destinationsConfig);

        $this->configProvider->method('get')->willReturn($config);

        // Act
        $destination = $this->factory->build(DestinationType::YouTrack);

        // Assert
        $this->assertInstanceOf(YouTrackDestination::class, $destination);
    }

    public function testBuildYouTrackDestinationThrowsExceptionWhenConfigMissing(): void
    {
        // Arrange
        $destinationsConfig = new DestinationsConfig(youTrack: null);
        $config = $this->createConfig($destinationsConfig);

        $this->configProvider->method('get')->willReturn($config);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('YouTrack destination configuration is missing');

        // Act
        $this->factory->build(DestinationType::YouTrack);
    }

    private function createConfig(DestinationsConfig $destinations): Config
    {
        return new Config(
            source: SourceType::PlainJson,
            destination: DestinationType::YouTrack,
            sources: $this->createStub(SourcesConfig::class),
            destinations: $destinations,
        );
    }
}
