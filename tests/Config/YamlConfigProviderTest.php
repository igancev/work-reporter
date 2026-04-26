<?php

declare(strict_types=1);

namespace Tests\Config;

use Igancev\WorkReporter\Config\YamlConfigProvider;
use Igancev\WorkReporter\Destination\DestinationType;
use Igancev\WorkReporter\Source\SourceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

        $this->expectException(RuntimeException::class);
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
        $second = $provider->get();

        $this->assertSame($first, $second);
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
