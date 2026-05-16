<?php

declare(strict_types=1);

namespace Tests\Unit\Source\SuperProductivity;

use Igancev\WorkReporter\Source\SuperProductivity\Tag;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Tag::class)]
final class TagTest extends TestCase
{
    public function testConstructor(): void
    {
        // Arrange
        $id = 'tag-id';
        $name = 'Tag Name';

        // Act
        $tag = new Tag($id, $name);

        // Assert
        $this->assertSame($id, $tag->id);
        $this->assertSame($name, $tag->name);
    }

    #[DataProvider('invalidIdProvider')]
    public function testConstructorThrowsExceptionForInvalidId(string $id): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag id cannot be empty');

        // Act
        new Tag($id, 'Valid Name');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIdProvider(): array
    {
        return [
            'empty string' => [''],
            'only spaces' => ['   '],
            'tab character' => ["\t"],
        ];
    }

    #[DataProvider('invalidNameProvider')]
    public function testConstructorThrowsExceptionForInvalidName(string $name): void
    {
        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tag name cannot be empty');

        // Act
        new Tag('valid-id', $name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNameProvider(): array
    {
        return [
            'empty string' => [''],
            'only spaces' => ['   '],
            'tab character' => ["\t"],
        ];
    }
}
