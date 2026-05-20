<?php

declare(strict_types=1);

namespace Tests\Unit;

use Igancev\WorkReporter\Banner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

#[CoversClass(Banner::class)]
final class BannerTest extends TestCase
{
    public function testPlainReturnsNonEmptyArray(): void
    {
        // Act
        $lines = Banner::plain();

        // Assert
        $this->assertNotEmpty($lines);
    }

    public function testPlainLinesHaveConsistentWidth(): void
    {
        // Act
        $lines = Banner::plain();
        $widths = array_map(mb_strlen(...), $lines);

        // Assert
        $this->assertSame(1, count(array_unique($widths)));
    }

    public function testRenderWritesToOutput(): void
    {
        // Arrange
        $output = new BufferedOutput();

        // Act
        Banner::render($output);

        // Assert
        $content = $output->fetch();
        $this->assertNotEmpty($content);
        $this->assertMatchesRegularExpression('/\x1b\[38;2;\d+;\d+;\d+m/', $content);
        $this->assertStringContainsString("\033[0m", $content);
    }

    public function testRenderOutputContainsBlockCharacters(): void
    {
        // Arrange
        $output = new BufferedOutput();

        // Act
        Banner::render($output);

        // Assert
        $content = $output->fetch();
        $this->assertMatchesRegularExpression('/[█▀▄]/', $content);
    }

    public function testRenderOutputContainsTagline(): void
    {
        // Arrange
        $output = new BufferedOutput();

        // Act
        Banner::render($output, '1.2.3');

        // Assert
        $content = $output->fetch();
        $this->assertStringContainsString('v1.2.3', $content);
        $this->assertStringContainsString('GitHub', $content);
        $this->assertStringContainsString('https://github.com/igancev/work-reporter', $content);
        $this->assertStringContainsString('❤', $content);
        $this->assertStringContainsString('Please star us if you find it useful', $content);
    }

    public function testRenderContainsBothGradients(): void
    {
        // Arrange
        $output = new BufferedOutput();

        // Act
        Banner::render($output);

        // Assert
        $content = $output->fetch();
        preg_match_all('/\x1b\[38;2;(\d+);(\d+);(\d+)m/', $content, $matches, PREG_SET_ORDER);
        $this->assertNotEmpty($matches);

        $hasBlue = false;
        $hasPink = false;

        foreach ($matches as $match) {
            $r = (int) $match[1];
            $b = (int) $match[3];

            if ($r < 150 && $b > 150) {
                $hasBlue = true;
            }

            if ($r > 200 && $b > 100) {
                $hasPink = true;
            }
        }

        $this->assertTrue($hasBlue, 'Expected blue gradient colors for WORK');
        $this->assertTrue($hasPink, 'Expected pink gradient colors for REPORTER');
    }

    public function testPlainReturnsExactBannerContent(): void
    {
        // Act
        $lines = Banner::plain();

        // Assert
        $this->assertCount(3, $lines);
        $this->assertSame(
            '█   █ █▀█ █▀▄ █▄▀    █▀▄ █▀▀ █▀▄ █▀█ █▀▄ ▀█▀ █▀▀ █▀▄',
            $lines[0],
        );
        $this->assertSame(
            '█ █ █ █ █ █▄▀ ██  ▄▄ █▄▀ █▀  █▄▀ █ █ █▄▀  █  █▀  █▄▀',
            $lines[1],
        );
        $this->assertSame(
            '▀▀ ▀▀ ▀▀▀ ▀ ▀ ▀ ▀    ▀ ▀ ▀▀▀ ▀   ▀▀▀ ▀ ▀  ▀  ▀▀▀ ▀ ▀',
            $lines[2],
        );
    }

    public function testRenderOutputMatchesPlainTextStructure(): void
    {
        // Arrange
        $output = new BufferedOutput();

        // Act
        Banner::render($output);

        // Assert
        $content = $output->fetch();
        $outputLines = explode("\n", $content);

        // First line should be empty (the leading writeln(''))
        $this->assertSame('', $outputLines[0], 'Render output should start with an empty line');

        // Strip ANSI codes from banner lines and compare with plain() text
        $plainLines = Banner::plain();
        $strippedBannerLines = [];
        for ($i = 1; $i <= count($plainLines); $i++) {
            $stripped = preg_replace('/\033(?:\[[0-9;]*m|\]8;;[^\033]*\033\\\\)/', '', $outputLines[$i]);
            $strippedBannerLines[] = $stripped;
        }

        foreach ($plainLines as $idx => $expected) {
            $this->assertSame($expected, $strippedBannerLines[$idx]);
        }
    }
}
