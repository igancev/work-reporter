<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Igancev\WorkReporter\Config\ConfigException;
use Igancev\WorkReporter\Config\YamlConfigProvider;
use Igancev\WorkReporter\Destination\DestinationType;
use Igancev\WorkReporter\Source\SourceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlConfigProvider::class)]
final class YamlConfigProviderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/work-reporter-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            array_map('unlink', $files);
        }
        rmdir($this->tempDir);
    }

    public function testThrowsExceptionWhenConfigFileNotFound(): void
    {
        $provider = new YamlConfigProvider($this->tempDir . '/nonexistent.yaml');

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file not found at');

        $provider->get();
    }

    public function testParsesFullConfig(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: superProductivity
destination: youTrack
sources:
  superProductivity:
    syncFilePath: /tmp/sp_sync
  plainJson:
    filePath: /tmp/plain.json
destinations:
  youTrack:
    url: http://localhost:8080
    token: test-token-123
YAML);

        $provider = new YamlConfigProvider($configPath);
        $config = $provider->get();

        $this->assertSame(SourceType::SuperProductivity, $config->source);
        $this->assertSame(DestinationType::YouTrack, $config->destination);
        $this->assertNotNull($config->sources->superProductivity);
        $this->assertSame('/tmp/sp_sync', $config->sources->superProductivity->syncFilePath);
        $this->assertNotNull($config->sources->plainJson);
        $this->assertSame('/tmp/plain.json', $config->sources->plainJson->filePath);
        $this->assertNotNull($config->destinations->youTrack);
        $this->assertSame('http://localhost:8080', $config->destinations->youTrack->url);
        $this->assertSame('test-token-123', $config->destinations->youTrack->token);
    }

    public function testParsesMinimalConfig(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources: []
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);
        $config = $provider->get();

        $this->assertSame(SourceType::PlainJson, $config->source);
        $this->assertSame(DestinationType::YouTrack, $config->destination);
        $this->assertNull($config->sources->superProductivity);
        $this->assertNull($config->sources->plainJson);
        $this->assertNull($config->destinations->youTrack);
    }

    public function testCachesConfigOnSubsequentCalls(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources: []
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);
        $first = $provider->get();
        file_put_contents($configPath, 'corrupted');
        $second = $provider->get();

        $this->assertSame($first, $second);
    }

    public function testThrowsExceptionOnInvalidYamlSyntax(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, "source: {\ninvalid yaml\n");

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid YAML syntax in config file');

        $provider->get();
    }

    public function testThrowsExceptionOnEmptyConfigFile(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, '');

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Configuration file is empty or has invalid structure');

        $provider->get();
    }

    public function testThrowsExceptionWhenSourceKeyMissing(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
destination: youTrack
sources: []
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing required config key: "source"');

        $provider->get();
    }

    public function testThrowsExceptionWhenSourceKeyIsNotString(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source:
  - one
  - two
destination: youTrack
sources: []
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config key "source" must be a string, got array');

        $provider->get();
    }

    public function testThrowsExceptionWhenSourcesKeyIsNotMapping(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources: "not a mapping"
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config key "sources" must be a mapping, got string');

        $provider->get();
    }

    public function testThrowsExceptionWhenDestinationsKeyIsNotMapping(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources: []
destinations: "not a mapping"
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config key "destinations" must be a mapping, got string');

        $provider->get();
    }

    public function testThrowsExceptionWhenYouTrackConfigIsNotMapping(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources: []
destinations:
  youTrack: not-a-mapping
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid youTrack destination config: expected a mapping.');

        $provider->get();
    }

    public function testThrowsExceptionWhenDestinationKeyIsNotString(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination:
  - one
  - two
sources: []
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config key "destination" must be a string, got array');

        $provider->get();
    }

    public function testThrowsExceptionWhenDestinationKeyMissing(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
sources: []
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing required config key: "destination"');

        $provider->get();
    }

    public function testThrowsExceptionOnInvalidSourceType(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: unknown
destination: youTrack
sources: []
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid source type: "unknown"');

        $provider->get();
    }

    public function testThrowsExceptionOnInvalidDestinationType(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: jira
sources: []
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Invalid destination type: "jira"');

        $provider->get();
    }

    public function testThrowsExceptionWhenYouTrackMissingUrl(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources: []
destinations:
  youTrack:
    token: abc
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing required key "url" in destinations.youTrack config');

        $provider->get();
    }

    public function testThrowsExceptionWhenYouTrackMissingToken(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources: []
destinations:
  youTrack:
    url: http://yt.local
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing required key "token" in destinations.youTrack config');

        $provider->get();
    }

    public function testThrowsExceptionWhenPlainJsonMissingFilePath(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources:
  plainJson:
    wrong: value
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing required key "filePath" in sources.plainJson config');

        $provider->get();
    }

    public function testThrowsExceptionWhenSuperProductivityMissingSyncFilePath(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: superProductivity
destination: youTrack
sources:
  superProductivity:
    wrong: value
destinations: []
YAML);

        $provider = new YamlConfigProvider($configPath);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Missing required key "syncFilePath" in sources.superProductivity config');

        $provider->get();
    }

    public function testParsesConfigWithOnlySuperProductivitySource(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: superProductivity
destination: youTrack
sources:
  superProductivity:
    syncFilePath: /tmp/sync
destinations:
  youTrack:
    url: http://yt.local
    token: abc
YAML);

        $provider = new YamlConfigProvider($configPath);
        $config = $provider->get();

        $this->assertNotNull($config->sources->superProductivity);
        $this->assertNull($config->sources->plainJson);
        $this->assertSame('/tmp/sync', $config->sources->superProductivity->syncFilePath);
    }

    public function testParsesConfigWithOnlyPlainJsonSource(): void
    {
        $configPath = $this->tempDir . '/config.yaml';
        file_put_contents($configPath, <<<YAML
source: plainJson
destination: youTrack
sources:
  plainJson:
    filePath: /tmp/data.json
destinations:
  youTrack:
    url: http://yt.local
    token: xyz
YAML);

        $provider = new YamlConfigProvider($configPath);
        $config = $provider->get();

        $this->assertNull($config->sources->superProductivity);
        $this->assertNotNull($config->sources->plainJson);
        $this->assertSame('/tmp/data.json', $config->sources->plainJson->filePath);
    }
}
