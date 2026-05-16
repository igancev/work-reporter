<?php

declare(strict_types=1);

namespace Tests\Unit\Source\SuperProductivity;

use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\Source\SuperProductivity\Tag;
use Igancev\WorkReporter\Source\SuperProductivity\Task;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[CoversClass(Task::class)]
class TaskTest extends TestCase
{
    public function testConstructThrowsExceptionIfSubtaskHasSubtasks(): void
    {
        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Subtask cannot have subtasks');

        // Act
        new Task(
            id: '1',
            parentTitle: 'Parent',
            title: 'Subtask',
            subTasks: [
                new Task('2', 'Subtask', 'Inner', [], [], []),
            ],
            timeSpentOnDay: [],
            tags: [],
        );
    }

    public function testIsSubtask(): void
    {
        // Act
        $task = new Task('1', null, 'Task', [], [], []);
        $subtask = new Task('2', 'Parent', 'Subtask', [], [], []);

        // Assert
        $this->assertFalse($task->isSubtask());
        $this->assertTrue($subtask->isSubtask());
    }

    public function testHasSpentOnDays(): void
    {
        // Arrange
        $task = new Task(
            id: '1',
            parentTitle: null,
            title: 'Task',
            subTasks: [],
            timeSpentOnDay: ['2023-01-01' => 1000],
            tags: [],
        );

        // Act & Assert
        $this->assertTrue($task->hasSpentOnDays(['2023-01-01']));
        $this->assertTrue($task->hasSpentOnDays(['2023-01-02', '2023-01-01']));
        $this->assertFalse($task->hasSpentOnDays(['2023-01-02']));
    }

    public function testHasSpentOnDaysThrowsExceptionOnInvalidFormat(): void
    {
        // Arrange
        $task = new Task('1', null, 'Task', [], [], []);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid date format');

        // Act
        $task->hasSpentOnDays(['invalid-date']);
    }

    public function testGetDurationByDay(): void
    {
        // Arrange
        $task = new Task(
            id: '1',
            parentTitle: null,
            title: 'Task',
            subTasks: [],
            timeSpentOnDay: ['2023-01-01' => 3600_000], // 1 hour
            tags: [],
        );

        // Act
        $duration = $task->getDurationByDay('2023-01-01');

        // Assert
        $this->assertInstanceOf(Duration::class, $duration);
        $this->assertSame(3600_000, $duration->toMilliseconds());
    }

    public function testHasDurationByDay(): void
    {
        // Arrange
        $task = new Task(
            id: '1',
            parentTitle: null,
            title: 'Task',
            subTasks: [],
            timeSpentOnDay: ['2023-01-01' => 1000],
            tags: [],
        );

        // Act & Assert
        $this->assertTrue($task->hasDurationByDay('2023-01-01'));
        $this->assertFalse($task->hasDurationByDay('2023-01-02'));
    }

    public function testGetTaskId(): void
    {
        // Arrange
        $task = new Task('1', null, 'PROJ-123 Title', [], [], []);
        $subtask = new Task('2', 'PARENT-456 Parent', 'Subtask', [], [], []);

        // Act & Assert
        $this->assertSame('PROJ-123', $task->getTaskId());
        $this->assertSame('PARENT-456', $subtask->getTaskId());
    }

    public function testGetTaskIdThrowsExceptionIfNoIdFound(): void
    {
        $task = new Task('1', null, 'No ID here', [], [], []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to parse task id from title "No ID here"');

        $task->getTaskId();
    }

    public function testWorkTypeFromTags(): void
    {
        $tag = new Tag('tag-1', 'Development');
        $task = new Task('1', null, 'Task', [], [], [$tag]);

        $this->assertSame('Development', $task->workType());
    }

    public function testWorkTypeFromTitleBrackets(): void
    {
        $task = new Task('1', null, '[Research] Deep dive', [], [], []);
        $this->assertSame('Research', $task->workType());
    }

    public function testWorkTypeFromTitleFirstWord(): void
    {
        $task = new Task('1', null, 'Meeting with team', [], [], []);
        $this->assertSame('Meeting', $task->workType());
    }

    public function testWorkTypeFromParentTitle(): void
    {
        $task = new Task('1', 'Parent Title', '   ', [], [], []);
        $this->assertSame('Parent', $task->workType());
    }

    public function testWorkTypeDefault(): void
    {
        $task = new Task('1', null, '   ', [], [], []);
        $this->assertSame('Встречи', $task->workType());
    }

    public function testWorkTypeFallbackToParentIfTitleProducesNoWorkType(): void
    {
        // Title has only punctuation/spaces which parseWorkType might return null for if it fails both regexes
        // Actually parseWorkType returns null if trim is empty.
        $task = new Task('1', '[ParentType] Parent', '!!!', [], [], []);
        $this->assertSame('ParentType', $task->workType());
    }

    #[DataProvider('parseWorkTypeDataProvider')]
    public function testParseWorkType(?string $expected, string $title): void
    {
        $task = new Task(
            id: '1',
            parentTitle: null,
            title: 'dummy',
            subTasks: [],
            timeSpentOnDay: [],
            tags: [],
        );

        $reflection = new ReflectionMethod($task, 'parseWorkType');
        $result = $reflection->invoke($task, $title);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{null|string, string}>
     */
    public static function parseWorkTypeDataProvider(): array
    {
        return [
            'word in square brackets' => ['Development', '[Development] Feature implementation'],
            'word in square brackets not at start' => ['Development', 'Feature [Development] implementation'],
            'first word if no brackets' => ['Feature', 'Feature implementation'],
            'first word with punctuation' => ['Feature', 'Feature, implementation'],
            'empty title' => [null, ''],
            'only spaces' => [null, '   '],
            'only nbsp' => [null, "\u{00A0}\u{00A0}"],
            'mixed spaces and nbsp' => [null, " \u{00A0}  "],
            'brackets with spaces inside' => ['Development', '  [Development]  '],
            'no brackets, multiple words' => ['Hello', 'Hello world'],
            'first word is a number' => ['123', '123 test'],
        ];
    }
}
