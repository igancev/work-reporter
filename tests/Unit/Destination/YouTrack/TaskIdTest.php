<?php

declare(strict_types=1);

namespace Tests\Unit\Destination\YouTrack;

use Igancev\WorkReporter\Destination\YouTrack\TaskId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskId::class)]
final class TaskIdTest extends TestCase
{
    public function testFromString(): void
    {
        // Arrange
        $idString = 'PROJ-123';

        // Act
        $taskId = TaskId::fromString($idString);

        // Assert
        $this->assertSame('PROJ', $taskId->getProjectAlias());
        $this->assertSame(123, $taskId->getNumericId());
        $this->assertSame($idString, $taskId->toString());
    }

    #[DataProvider('provideValidTaskIds')]
    public function testToString(string $alias, int $numericId, string $expected): void
    {
        // Arrange
        $taskIdString = "{$alias}-{$numericId}";

        // Act
        $taskId = TaskId::fromString($taskIdString);

        // Assert
        $this->assertSame($expected, $taskId->toString());
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function provideValidTaskIds(): array
    {
        return [
            'simple' => ['ABC', 1, 'ABC-1'],
            'with leading zero in string but int in result' => ['PROJ', 012, 'PROJ-10'], // 012 is octal 10 in PHP
            'with zero' => ['TEST', 0, 'TEST-0'],
        ];
    }
}
