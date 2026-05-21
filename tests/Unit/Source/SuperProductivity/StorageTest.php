<?php

declare(strict_types=1);

namespace Tests\Unit\Source\SuperProductivity;

use Igancev\WorkReporter\Source\SourceException;
use Igancev\WorkReporter\Source\SuperProductivity\Storage;
use Igancev\WorkReporter\Source\SuperProductivity\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Storage::class)]
final class StorageTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'sp_storage_test');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testConstructorParsesValidJsonWithPrefix(): void
    {
        // Arrange
        $data = [
            'mainModelData' => [
                'task' => ['ids' => [], 'entities' => []],
                'archiveYoung' => ['task' => ['ids' => [], 'entities' => []]],
                'archiveOld' => ['task' => ['ids' => [], 'entities' => []]],
            ],
        ];
        file_put_contents($this->tempFile, 'pf_4.4__' . json_encode($data));

        // Act
        $storage = new Storage($this->tempFile);

        // Assert
        $this->assertInstanceOf(Storage::class, $storage);
    }

    public function testGetTaskIds(): void
    {
        // Arrange
        $data = [
            'mainModelData' => [
                'task' => ['ids' => ['t1', 't2'], 'entities' => []],
                'archiveYoung' => ['task' => ['ids' => ['t3'], 'entities' => []]],
                'archiveOld' => ['task' => ['ids' => ['t4'], 'entities' => []]],
            ],
        ];
        file_put_contents($this->tempFile, 'pf_4.4__' . json_encode($data));
        $storage = new Storage($this->tempFile);

        // Act
        $taskIds = [...$storage->getTaskIds()];

        // Assert
        $this->assertSame(['t1', 't2', 't3', 't4'], $taskIds);
    }

    public function testGetTaskByIdFromDifferentSources(): void
    {
        // Arrange
        $data = [
            'mainModelData' => [
                'task' => [
                    'entities' => ['t1' => ['id' => 't1', 'title' => 'Task 1']],
                ],
                'archiveYoung' => [
                    'task' => [
                        'entities' => ['t2' => ['id' => 't2', 'title' => 'Task 2']],
                    ],
                ],
                'archiveOld' => [
                    'task' => [
                        'entities' => ['t3' => ['id' => 't3', 'title' => 'Task 3']],
                    ],
                ],
            ],
        ];
        file_put_contents($this->tempFile, 'pf_4.4__' . json_encode($data));
        $storage = new Storage($this->tempFile);

        // Act & Assert
        $this->assertSame('Task 1', $storage->getTaskById('t1')['title']);
        $this->assertSame('Task 2', $storage->getTaskById('t2')['title']);
        $this->assertSame('Task 3', $storage->getTaskById('t3')['title']);
    }

    public function testGetTaskByIdThrowsExceptionIfNotFound(): void
    {
        // Arrange
        $data = [
            'mainModelData' => [
                'task' => ['entities' => []],
                'archiveYoung' => ['task' => ['entities' => []]],
                'archiveOld' => ['task' => ['entities' => []]],
            ],
        ];
        file_put_contents($this->tempFile, 'pf_4.4__' . json_encode($data));
        $storage = new Storage($this->tempFile);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('SuperProductivitySyncDataSource: Unable to find task with id non-existent');

        // Act
        $storage->getTaskById('non-existent');
    }

    public function testGetTagById(): void
    {
        // Arrange
        $data = [
            'mainModelData' => [
                'task' => ['entities' => []],
                'archiveYoung' => ['task' => ['entities' => []]],
                'archiveOld' => ['task' => ['entities' => []]],
                'tag' => [
                    'entities' => [
                        'tag1' => ['title' => 'Tag 1'],
                    ],
                ],
            ],
        ];
        file_put_contents($this->tempFile, 'pf_4.4__' . json_encode($data));
        $storage = new Storage($this->tempFile);

        // Act
        $tag = $storage->getTagById('tag1');

        // Assert
        $this->assertInstanceOf(Tag::class, $tag);
        $this->assertSame('tag1', $tag->id);
        $this->assertSame('Tag 1', $tag->name);
    }

    public function testGetTagByIdThrowsExceptionIfNotFound(): void
    {
        // Arrange
        $data = [
            'mainModelData' => [
                'task' => ['entities' => []],
                'archiveYoung' => ['task' => ['entities' => []]],
                'archiveOld' => ['task' => ['entities' => []]],
                'tag' => ['entities' => []],
            ],
        ];
        file_put_contents($this->tempFile, 'pf_4.4__' . json_encode($data));
        $storage = new Storage($this->tempFile);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('SuperProductivitySyncDataSource: Unable to find tag with id non-existent');

        // Act
        $storage->getTagById('non-existent');
    }

    public function testParseJsonThrowsExceptionIfFileNotReadable(): void
    {
        // Arrange
        $unreadableFile = '/tmp/unreadable_' . uniqid();
        touch($unreadableFile);
        chmod($unreadableFile, 0000);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to read sync data file: ' . $unreadableFile,
        );

        try {
            // Act
            new Storage($unreadableFile);
        } finally {
            unlink($unreadableFile);
        }
    }

    public function testParseJsonThrowsExceptionIfNoJsonFound(): void
    {
        // Arrange
        file_put_contents($this->tempFile, 'no-curly-braces-here');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to parse start position "{" ' . $this->tempFile,
        );

        // Act
        new Storage($this->tempFile);
    }

    public function testParseJsonThrowsExceptionOnInvalidJson(): void
    {
        // Arrange
        file_put_contents($this->tempFile, 'pf_4.4__{invalid-json}');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('SuperProductivitySyncDataSource: Unable to parse JSON:');

        // Act
        new Storage($this->tempFile);
    }
}
