<?php

declare(strict_types=1);

namespace Tests\Unit;

use Igancev\WorkReporter\InitCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(InitCommand::class)]
class InitCommandTest extends TestCase
{
    private string $tempDir;
    private string $configDir;
    private string $configPath;
    private string|false $originalHome;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/work-reporter-test-' . uniqid();
        $this->configDir = $this->tempDir . '/.config/work-reporter';
        $this->configPath = $this->configDir . '/config.yaml';
        $this->originalHome = getenv('HOME');

        putenv('HOME=' . $this->tempDir);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }
        if (is_dir($this->configDir)) {
            rmdir($this->configDir);
        }
        $parentDir = dirname($this->configDir);
        if (is_dir($parentDir)) {
            rmdir($parentDir);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        if ($this->originalHome !== false) {
            putenv('HOME=' . $this->originalHome);
        }
    }

    public function testCreatesConfigFileSuccessfully(): void
    {
        $command = new InitCommand();
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertFileExists($this->configPath);
        $this->assertStringContainsString('Configuration file created', $tester->getDisplay());

        $content = (string) file_get_contents($this->configPath);
        $this->assertStringContainsString('source:', $content);
        $this->assertStringContainsString('destination:', $content);
        $this->assertStringContainsString('youTrack', $content);
    }

    public function testCreatesDirectoryIfNotExists(): void
    {
        $this->assertDirectoryDoesNotExist($this->configDir);

        $command = new InitCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertDirectoryExists($this->configDir);
        $this->assertFileExists($this->configPath);
    }

    public function testWarnsIfConfigAlreadyExists(): void
    {
        mkdir($this->configDir, 0755, true);
        file_put_contents($this->configPath, 'existing content');

        $command = new InitCommand();
        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('already exists', $tester->getDisplay());
        $this->assertSame('existing content', file_get_contents($this->configPath));
    }

    public function testOverwritesConfigWhenConfirmed(): void
    {
        mkdir($this->configDir, 0755, true);
        file_put_contents($this->configPath, 'old content');

        $command = new InitCommand();
        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);

        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertNotSame('old content', file_get_contents($this->configPath));
        $this->assertStringContainsString('source:', (string) file_get_contents($this->configPath));
    }

    public function testFailsIfDirectoryCannotBeCreated(): void
    {
        // Create a file with the same name as the intended config directory
        // This makes mkdir() fail
        $parentDir = dirname($this->configDir);
        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }
        file_put_contents($this->configDir, 'I am a file, not a directory');

        $command = new InitCommand();
        $tester = new CommandTester($command);

        // Expect FAILURE status
        $status = @$tester->execute([]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Failed to create directory', $tester->getDisplay());

        // Cleanup
        unlink($this->configDir);
    }

    public function testFailsIfFileCannotBeWritten(): void
    {
        // Create the directory but make it read-only
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0755, true);
        }
        chmod($this->configDir, 0555);

        $command = new InitCommand();
        $tester = new CommandTester($command);

        // Expect FAILURE status
        // Use @ to suppress the warning from file_put_contents
        $status = @$tester->execute([]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Failed to write configuration file', $tester->getDisplay());

        // Restore permissions for cleanup
        chmod($this->configDir, 0755);
    }
}
