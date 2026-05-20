<?php

declare(strict_types=1);

namespace Tests\Unit;

use Igancev\WorkReporter\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    public function testVersionOptionRendersBanner(): void
    {
        // Arrange
        $app = new Application('work-reporter', '1.2.3');
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')
            ->with(['--version', '-V'], true)
            ->willReturn(true);
        $output = new BufferedOutput();

        // Act
        $status = $app->doRun($input, $output);

        // Assert
        $this->assertSame(0, $status);
        $content = $output->fetch();
        $this->assertStringContainsString('v1.2.3', $content);
        $this->assertMatchesRegularExpression('/[█▀▄]/', $content);
    }

    public function testShortVersionFlagRendersBanner(): void
    {
        // Arrange
        $app = new Application('work-reporter', '2.0.0');
        $input = $this->createMock(InputInterface::class);
        $input->method('hasParameterOption')
            ->with(['--version', '-V'], true)
            ->willReturn(true);
        $output = new BufferedOutput();

        // Act
        $status = $app->doRun($input, $output);

        // Assert
        $this->assertSame(0, $status);
        $this->assertStringContainsString('v2.0.0', $output->fetch());
    }

    public function testWithoutVersionFlagDelegatesToParent(): void
    {
        // Arrange
        $app = new Application('work-reporter', '1.0.0');
        $app->setAutoExit(false);

        $input = new ArgvInput(['console', 'list']);
        $output = new BufferedOutput();

        // Act
        $status = $app->run($input, $output);

        // Assert — no banner block characters in output, just the default list command
        $this->assertSame(0, $status);
        $this->assertStringNotContainsString('█', $output->fetch());
    }
}
