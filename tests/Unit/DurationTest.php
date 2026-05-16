<?php

declare(strict_types=1);

namespace Tests\Unit;

use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\InvalidDurationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Duration::class)]
final class DurationTest extends TestCase
{
    public function testFromMilliseconds(): void
    {
        // Arrange
        $ms = 123456;

        // Act
        $duration = Duration::fromMilliseconds($ms);

        // Assert
        $this->assertSame($ms, $duration->toMilliseconds());
    }

    public function testThrowsExceptionForNegativeMilliseconds(): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Milliseconds must be positive');

        // Act
        Duration::fromMilliseconds(-1);
    }

    #[DataProvider('toMinutesProvider')]
    public function testToMinutes(int $ms, int $expected): void
    {
        // Arrange
        $duration = Duration::fromMilliseconds($ms);

        // Act & Assert
        $this->assertSame($expected, $duration->toMinutes());
    }

    public function testFromMinutes(): void
    {
        // Act
        $duration = Duration::fromMinutes(5);

        // Assert
        $this->assertSame(300000, $duration->toMilliseconds());
    }

    public function testAddImmutable(): void
    {
        // Arrange
        $d1 = Duration::fromMinutes(10);
        $d2 = Duration::fromMinutes(20);

        // Act
        $result = $d1->add($d2);

        // Assert
        $this->assertSame(30 * 60000, $result->toMilliseconds());
        $this->assertNotSame($d1, $result);
        $this->assertNotSame($d2, $result);
        // Verify immutability
        $this->assertSame(10 * 60000, $d1->toMilliseconds());
    }

    #[DataProvider('comparisonProvider')]
    public function testComparison(int $ms1, int $ms2, bool $equals, bool $isLess, bool $isGreater): void
    {
        // Arrange
        $d1 = Duration::fromMilliseconds($ms1);
        $d2 = Duration::fromMilliseconds($ms2);

        // Act & Assert
        $this->assertSame($equals, $d1->equals($d2));
        $this->assertSame($isLess, $d1->isLessThan($d2));
        $this->assertSame($isGreater, $d1->isGreaterThan($d2));
    }

    /**
     * @return array<string, array{int, int, bool, bool, bool}>
     */
    public static function comparisonProvider(): array
    {
        return [
            'equal' => [1000, 1000, true, false, false],
            'less' => [1000, 2000, false, true, false],
            'greater' => [2000, 1000, false, false, true],
        ];
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function toMinutesProvider(): array
    {
        return [
            'zero' => [0, 0],
            'less than half minute (rounds to 0)' => [29999, 0],
            'half minute (rounds to 1)' => [30000, 1],
            'more than half minute (rounds to 1)' => [30001, 1],
            'one minute' => [60000, 1],
            'one hour' => [3600000, 60],
            'large value' => [600_000_000, 10000],
        ];
    }

    #[DataProvider('successFromStringProvider')]
    public function testFromString(string $input, int $expectedMs): void
    {
        // Act
        $duration = Duration::fromString($input);

        // Assert
        $this->assertSame($expectedMs, $duration->toMilliseconds());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function successFromStringProvider(): array
    {
        return [
            'only minutes' => ['30m', 30 * 60000],
            'only hours' => ['2h', 120 * 60000],
            'hours and minutes' => ['1h 30m', 90 * 60000],
            'hours and minutes mixed' => ['30m 1h', 90 * 60000],
            'numeric (assumed minutes)' => ['420', 420 * 60000],
            'zero as string' => ['0', 0],
            'zero minutes' => ['0m', 0],
            'zero hours' => ['0h', 0],
            'case insensitive' => ['1H 15M', 75 * 60000],
            'with extra spaces' => ['  2h   5m  ', 125 * 60000],
        ];
    }

    #[DataProvider('durationProvider')]
    public function testToDuration(int $ms, string $expected): void
    {
        // Arrange
        $duration = Duration::fromMilliseconds($ms);

        // Act & Assert
        $this->assertSame($expected, $duration->toString());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function durationProvider(): array
    {
        return [
            'zero' => [0, '0m'],
            'less than minute (rounds to 0)' => [29999, '0m'],
            'less than minute (rounds to 1)' => [30000, '1m'],
            'one minute' => [60000, '1m'],
            '59 minutes' => [59 * 60000, '59m'],
            'one hour' => [60 * 60000, '1h'],
            'one hour and one minute' => [61 * 60000, '1h 1m'],
            'one hour and 59 minutes' => [119 * 60000, '1h 59m'],
            'two hours' => [120 * 60000, '2h'],
            'complex value' => [3661000, '1h 1m'], // 3661000 ms = 61.016 minutes -> 61 minutes -> 1h 1m
            'rounding up to hour' => [3570000, '1h'], // 3,570,000 ms = 59.5 minutes -> 60 minutes -> 1h
            'explicit 45m' => [45 * 60000, '45m'],
            'large duration (24h)' => [86400000, '24h'],
        ];
    }

    #[DataProvider('invalidStringProvider')]
    public function testFromStringThrowsExceptionForInvalidInput(string $input): void
    {
        // Assert
        $this->expectException(InvalidDurationException::class);

        // Act
        Duration::fromString($input);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidStringProvider(): array
    {
        return [
            'empty string' => [''],
            'non-numeric junk' => ['abc'],
            'malformed suffix' => ['1x'],
            'partial malformed' => ['1h 2x'],
            'negative minutes as string' => ['-10m'],
            'only space' => [' '],
            'mixed unit and naked number' => ['1h 30'],
            'too large value (overflow)' => [(string) PHP_INT_MAX],
            'overflow boundary with unit' => [intdiv(PHP_INT_MAX, 60000) + 1 . 'm'],
        ];
    }
}
