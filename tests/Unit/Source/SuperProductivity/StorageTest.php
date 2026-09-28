<?php

declare(strict_types=1);

namespace Tests\Unit\Source\SuperProductivity;

use Igancev\WorkReporter\Source\SourceException;
use Igancev\WorkReporter\Source\SuperProductivity\Storage;
use Igancev\WorkReporter\Source\SuperProductivity\Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->writeSyncData(self::oldFormatData());

        // Act
        $storage = new Storage($this->tempFile);

        // Assert
        $this->assertInstanceOf(Storage::class, $storage);
    }

    public function testGetTaskIds(): void
    {
        // Arrange
        $data = self::oldFormatData();
        $data['mainModelData']['task']['ids'] = ['t1', 't2'];
        $data['mainModelData']['archiveYoung']['task']['ids'] = ['t3'];
        $data['mainModelData']['archiveOld']['task']['ids'] = ['t4'];
        $this->writeSyncData($data);
        $storage = new Storage($this->tempFile);

        // Act
        $taskIds = [...$storage->getTaskIds()];

        // Assert
        $this->assertSame(['t1', 't2', 't3', 't4'], $taskIds);
    }

    public function testGetTaskByIdFromDifferentSources(): void
    {
        // Arrange
        $data = self::oldFormatData();
        $data['mainModelData']['task']['entities'] = ['t1' => self::taskEntity('t1', 'Task 1')];
        $data['mainModelData']['archiveYoung']['task']['entities'] = ['t2' => self::taskEntity('t2', 'Task 2')];
        $data['mainModelData']['archiveOld']['task']['entities'] = ['t3' => self::taskEntity('t3', 'Task 3')];
        $this->writeSyncData($data);
        $storage = new Storage($this->tempFile);

        // Act & Assert
        $this->assertSame('Task 1', $storage->getTaskById('t1')['title']);
        $this->assertSame('Task 2', $storage->getTaskById('t2')['title']);
        $this->assertSame('Task 3', $storage->getTaskById('t3')['title']);
    }

    public function testGetTaskByIdThrowsExceptionIfNotFound(): void
    {
        // Arrange
        $this->writeSyncData(self::oldFormatData());
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
        $data = self::oldFormatData();
        $data['mainModelData']['tag']['entities'] = ['tag1' => ['title' => 'Tag 1']];
        $this->writeSyncData($data);
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
        $this->writeSyncData(self::oldFormatData());
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
        $this->expectExceptionMessageMatches('/^SuperProductivitySyncDataSource: Unable to parse JSON: \S/');

        // Act
        new Storage($this->tempFile);
    }

    public function testGetTaskIdsFromNewFormatReadsTopLevelArchives(): void
    {
        // Arrange
        $this->writeSyncData(self::newFormatData(), 'pf_2__');
        $storage = new Storage($this->tempFile);

        // Act
        $taskIds = [...$storage->getTaskIds()];

        // Assert
        $this->assertSame(['a1', 'a2', 'a3'], $taskIds);
    }

    public function testGetTaskByIdFromNewFormatSections(): void
    {
        // Arrange
        $this->writeSyncData(self::newFormatData(), 'pf_2__');
        $storage = new Storage($this->tempFile);

        // Act & Assert
        $this->assertSame('Task 1', $storage->getTaskById('a1')['title']);
        $this->assertSame('Task 2', $storage->getTaskById('a2')['title']);
        $this->assertSame('Task 3', $storage->getTaskById('a3')['title']);
    }

    public function testArchiveResolutionPrefersTopLevelOverStateRemnant(): void
    {
        // Arrange
        $data = self::newFormatData();
        $data['state']['archiveYoung'] = [
            'task' => ['ids' => ['a2'], 'entities' => ['a2' => self::taskEntity('a2', 'State remnant')]],
        ];
        $this->writeSyncData($data, 'pf_2__');
        $storage = new Storage($this->tempFile);

        // Act & Assert
        $this->assertSame('Task 2', $storage->getTaskById('a2')['title']);
    }

    public function testRootResolutionPrefersMainModelDataOverState(): void
    {
        // Arrange
        $data = self::newFormatData();
        $data['mainModelData'] = self::oldFormatData()['mainModelData'];
        $data['mainModelData']['task'] = [
            'ids' => ['m1'],
            'entities' => ['m1' => self::taskEntity('m1', 'Old format task')],
        ];
        $this->writeSyncData($data, 'pf_4.4__');
        $storage = new Storage($this->tempFile);

        // Act
        $taskIds = [...$storage->getTaskIds()];

        // Assert
        $this->assertSame(['m1', 'a2', 'a3'], $taskIds);
    }

    public function testConstructorThrowsExceptionIfFormatIsNotRecognized(): void
    {
        // Arrange
        $this->writeSyncData(['foo' => 'bar']);

        try {
            // Act
            new Storage($this->tempFile);
            $this->fail('SourceException was not thrown');
        } catch (SourceException $e) {
            // Assert
            $this->assertSame(
                'SuperProductivitySyncDataSource: Unable to find "mainModelData" (sync format v1) or "state"'
                . ' (sync format v2) section in sync data file: ' . $this->tempFile,
                $e->getMessage(),
            );
            $this->assertSame(
                ['path' => $this->tempFile, 'topLevelKeys' => ['foo']],
                $e->getContext(),
            );
        }
    }

    public function testConstructorThrowsExceptionIfRootIsNotAnObject(): void
    {
        // Arrange
        $this->writeSyncData(['mainModelData' => 'corrupted']);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to find "mainModelData" (sync format v1) or "state"'
            . ' (sync format v2) section in sync data file: ' . $this->tempFile,
        );

        // Act
        new Storage($this->tempFile);
    }

    #[DataProvider('missingRootKeysProvider')]
    public function testConstructorThrowsExceptionIfRootKeyIsMissing(string $model, string $key): void
    {
        // Arrange
        $data = self::oldFormatData();
        unset($data['mainModelData'][$model][$key]);
        $this->writeSyncData($data);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to find "' . $model . '.' . $key
            . '" in sync data file: ' . $this->tempFile,
        );

        // Act
        new Storage($this->tempFile);
    }

    public function testConstructorThrowsExceptionIfTagEntitiesIsNotAnArray(): void
    {
        // Arrange
        $data = self::oldFormatData();
        $data['mainModelData']['tag']['entities'] = 'corrupted';
        $this->writeSyncData($data);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to find "tag.entities" in sync data file: ' . $this->tempFile,
        );

        // Act
        new Storage($this->tempFile);
    }

    #[DataProvider('missingArchiveKeysProvider')]
    public function testConstructorThrowsExceptionIfNewFormatArchiveIsMissing(string $archiveKey): void
    {
        // Arrange
        $data = self::newFormatData();
        unset($data[$archiveKey], $data['state'][$archiveKey]);
        $this->writeSyncData($data, 'pf_2__');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to find "' . $archiveKey . '" section in sync data file: '
            . $this->tempFile,
        );

        // Act
        new Storage($this->tempFile);
    }

    public function testConstructorThrowsExceptionIfOldFormatArchiveIsMissing(): void
    {
        // Arrange
        $data = self::oldFormatData();
        unset($data['mainModelData']['archiveOld']);
        $this->writeSyncData($data);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to find "archiveOld" section in sync data file: '
            . $this->tempFile,
        );

        // Act
        new Storage($this->tempFile);
    }

    public function testConstructorThrowsExceptionIfArchiveIsNotAnObject(): void
    {
        // Arrange
        $data = self::newFormatData();
        $data['archiveYoung'] = 'corrupted';
        $this->writeSyncData($data, 'pf_2__');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to find "archiveYoung" section in sync data file: '
            . $this->tempFile,
        );

        // Act
        new Storage($this->tempFile);
    }

    #[DataProvider('malformedArchiveTaskDataProvider')]
    public function testConstructorThrowsExceptionIfArchiveTaskDataIsMalformed(
        string $archiveKey,
        string $key,
    ): void {
        // Arrange
        $data = self::newFormatData();
        unset($data[$archiveKey]['task'][$key]);
        $this->writeSyncData($data, 'pf_2__');

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Unable to find "' . $archiveKey . '.task.' . $key
            . '" in sync data file: ' . $this->tempFile,
        );

        // Act
        new Storage($this->tempFile);
    }

    public function testGetTaskByIdThrowsExceptionIfTaskIsNotAnObject(): void
    {
        // Arrange
        $data = self::newFormatData();
        $data['state']['task']['entities']['a1'] = 'corrupted';
        $this->writeSyncData($data, 'pf_2__');
        $storage = new Storage($this->tempFile);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage('SuperProductivitySyncDataSource: Task with id a1 must be an object');

        // Act
        $storage->getTaskById('a1');
    }

    #[DataProvider('missingTaskKeysProvider')]
    public function testGetTaskByIdThrowsExceptionIfRequiredKeyIsMissing(string $key): void
    {
        // Arrange
        $rawTask = self::taskEntity('a1', 'Task 1');
        unset($rawTask[$key]);
        $data = self::newFormatData();
        $data['state']['task']['entities']['a1'] = $rawTask;
        $this->writeSyncData($data, 'pf_2__');
        $storage = new Storage($this->tempFile);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Task with id a1 is missing required key "' . $key . '"',
        );

        // Act
        $storage->getTaskById('a1');
    }

    public function testGetTagByIdThrowsExceptionIfTitleIsMissing(): void
    {
        // Arrange
        $data = self::newFormatData();
        $data['state']['tag']['entities']['tag1'] = ['id' => 'tag1'];
        $this->writeSyncData($data, 'pf_2__');
        $storage = new Storage($this->tempFile);

        // Assert
        $this->expectException(SourceException::class);
        $this->expectExceptionMessage(
            'SuperProductivitySyncDataSource: Tag with id tag1 is missing required key "title"',
        );

        // Act
        $storage->getTagById('tag1');
    }

    public function testResolvesTildePathViaHomeDirectory(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX-only path; Windows resolves USERPROFILE via HomeDirectory.');
        }

        // Arrange
        $homeDir = sys_get_temp_dir() . '/sp_storage_tilde_' . uniqid();
        mkdir($homeDir);
        file_put_contents($homeDir . '/sync.json', 'pf_2__' . json_encode(self::newFormatData()));
        $originalHome = getenv('HOME');
        putenv('HOME=' . $homeDir);

        try {
            // Act
            $storage = new Storage('~/sync.json');
            $taskIds = [...$storage->getTaskIds()];

            // Assert
            $this->assertSame(['a1', 'a2', 'a3'], $taskIds);
        } finally {
            putenv($originalHome === false ? 'HOME' : 'HOME=' . $originalHome);
            unlink($homeDir . '/sync.json');
            rmdir($homeDir);
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function missingRootKeysProvider(): array
    {
        return [
            'task.ids' => ['task', 'ids'],
            'task.entities' => ['task', 'entities'],
            'tag.entities' => ['tag', 'entities'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingArchiveKeysProvider(): array
    {
        return [
            'archiveYoung' => ['archiveYoung'],
            'archiveOld' => ['archiveOld'],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function malformedArchiveTaskDataProvider(): array
    {
        return [
            'archiveYoung without task.ids' => ['archiveYoung', 'ids'],
            'archiveYoung without task.entities' => ['archiveYoung', 'entities'],
            'archiveOld without task.ids' => ['archiveOld', 'ids'],
            'archiveOld without task.entities' => ['archiveOld', 'entities'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingTaskKeysProvider(): array
    {
        return [
            'id' => ['id'],
            'title' => ['title'],
            'timeSpentOnDay' => ['timeSpentOnDay'],
            'tagIds' => ['tagIds'],
            'subTaskIds' => ['subTaskIds'],
        ];
    }

    /**
     * @param array<mixed> $data
     */
    private function writeSyncData(array $data, string $prefix = 'pf_4.4__'): void
    {
        file_put_contents($this->tempFile, $prefix . json_encode($data));
    }

    /**
     * @return array<string, mixed>
     */
    private static function taskEntity(string $id, string $title): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'timeSpentOnDay' => [],
            'tagIds' => [],
            'subTaskIds' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function oldFormatData(): array
    {
        return [
            'mainModelData' => [
                'task' => ['ids' => [], 'entities' => []],
                'tag' => ['ids' => [], 'entities' => []],
                'archiveYoung' => ['task' => ['ids' => [], 'entities' => []]],
                'archiveOld' => ['task' => ['ids' => [], 'entities' => []]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function newFormatData(): array
    {
        return [
            'version' => 2,
            'state' => [
                'task' => ['ids' => ['a1'], 'entities' => ['a1' => self::taskEntity('a1', 'Task 1')]],
                'tag' => ['ids' => [], 'entities' => []],
                // empty remnants like in the real new-format file
                'archiveYoung' => ['task' => ['ids' => [], 'entities' => []]],
                'archiveOld' => ['task' => ['ids' => [], 'entities' => []]],
            ],
            // archive sections mirror the real new-format file key order
            'archiveYoung' => [
                'lastTimeTrackingFlush' => 0,
                'task' => ['ids' => ['a2'], 'entities' => ['a2' => self::taskEntity('a2', 'Task 2')]],
                'timeTracking' => ['tag' => [], 'project' => []],
            ],
            'archiveOld' => [
                'lastTimeTrackingFlush' => 0,
                'task' => ['ids' => ['a3'], 'entities' => ['a3' => self::taskEntity('a3', 'Task 3')]],
                'timeTracking' => ['tag' => [], 'project' => []],
            ],
        ];
    }
}
