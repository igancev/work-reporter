<?php

declare(strict_types=1);

namespace Tests\Unit;

use Igancev\WorkReporter\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wr-version-');
        if ($tmp === false) {
            self::fail('Could not create temporary file.');
        }
        $this->tmpFile = $tmp;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testReturnsDefaultWhenFileMissing(): void
    {
        unlink($this->tmpFile);
        self::assertSame('dev', Version::fromFile($this->tmpFile));
    }

    public function testReturnsDefaultWhenJsonIsMalformed(): void
    {
        file_put_contents($this->tmpFile, '{not json');
        self::assertSame('dev', Version::fromFile($this->tmpFile));
    }

    public function testReturnsDefaultWhenVersionKeyMissingOrEmpty(): void
    {
        file_put_contents($this->tmpFile, (string) json_encode(['version' => '']));
        self::assertSame('dev', Version::fromFile($this->tmpFile));

        file_put_contents($this->tmpFile, (string) json_encode(['other' => '1.0.0']));
        self::assertSame('dev', Version::fromFile($this->tmpFile));
    }

    public function testReturnsVersionStringWhenPresent(): void
    {
        file_put_contents($this->tmpFile, (string) json_encode(['version' => '1.2.3']));
        self::assertSame('1.2.3', Version::fromFile($this->tmpFile));
    }

    public function testReturnsDefaultWhenFileCannotBeRead(): void
    {
        chmod($this->tmpFile, 0000);

        try {
            self::assertSame('dev', Version::fromFile($this->tmpFile));
        } finally {
            chmod($this->tmpFile, 0644);
        }
    }
}
