<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Igancev\WorkReporter\Platform\HomeDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HomeDirectory::class)]
final class HomeDirectoryTest extends TestCase
{
    private string|false $originalHome;
    private string|false $originalUserProfile;
    private string|false $originalHomeDrive;
    private string|false $originalHomePath;

    protected function setUp(): void
    {
        // Arrange
        $this->originalHome = getenv('HOME');
        $this->originalUserProfile = getenv('USERPROFILE');
        $this->originalHomeDrive = getenv('HOMEDRIVE');
        $this->originalHomePath = getenv('HOMEPATH');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('HOME', $this->originalHome);
        $this->restoreEnv('USERPROFILE', $this->originalUserProfile);
        $this->restoreEnv('HOMEDRIVE', $this->originalHomeDrive);
        $this->restoreEnv('HOMEPATH', $this->originalHomePath);
    }

    public function testResolveReturnsHomeOnPosix(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX-only path; Windows uses USERPROFILE.');
        }

        // Arrange
        putenv('HOME=/home/posix-user');

        // Act
        $home = HomeDirectory::resolve();

        // Assert
        self::assertSame('/home/posix-user', $home);
    }

    public function testResolvePrefersUserProfileOnWindows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            self::markTestSkipped('Windows-only path; POSIX uses HOME.');
        }

        // Arrange
        putenv('USERPROFILE=C:\\Users\\winuser');
        putenv('HOMEDRIVE=Z:');
        putenv('HOMEPATH=\\Z-path');

        // Act
        $home = HomeDirectory::resolve();

        // Assert
        self::assertSame('C:\\Users\\winuser', $home);
    }

    public function testResolveFallsBackToHomeDrivePathOnWindowsWhenUserProfileEmpty(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            self::markTestSkipped('Windows-only fallback path.');
        }

        // Arrange
        putenv('USERPROFILE');
        putenv('HOMEDRIVE=C:');
        putenv('HOMEPATH=\\Users\\fallback');

        // Act
        $home = HomeDirectory::resolve();

        // Assert
        self::assertSame('C:\\Users\\fallback', $home);
    }

    public function testResolveReturnsEmptyStringWhenEnvUnset(): void
    {
        // Arrange
        if (PHP_OS_FAMILY === 'Windows') {
            putenv('USERPROFILE');
            putenv('HOMEDRIVE');
            putenv('HOMEPATH');
        } else {
            putenv('HOME');
        }

        // Act
        $home = HomeDirectory::resolve();

        // Assert
        self::assertSame('', $home);
    }

    private function restoreEnv(string $name, string|false $original): void
    {
        if ($original === false) {
            putenv($name);
            return;
        }
        putenv($name . '=' . $original);
    }
}
