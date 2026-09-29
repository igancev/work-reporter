<?php

declare(strict_types=1);

namespace Tests\Functional;

use Amp\Http\Client\HttpClientBuilder;
use DateTimeImmutable;
use Igancev\WorkReporter\Destination\DeliveryEvent;
use Igancev\WorkReporter\Destination\PipelineDeliveryStream;
use Igancev\WorkReporter\Destination\YouTrack\YouTrackDestination;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\TimeEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Wait\WaitForHttp;

#[CoversMethod(YouTrackDestination::class, 'logTimeEntries')]
#[CoversClass(PipelineDeliveryStream::class)]
class YouTrackDestinationTest extends TestCase
{
    private static StartedTestContainer $youtrackStartedContainer;

    public function testLogTimeEntriesToRealYouTrack(): void
    {
        // Arrange
        $destination = $this->createDestination();

        $entries = [
            new TimeEntry(
                taskId: 'DEMO-4',
                duration: Duration::fromString('1h 30m'),
                workType: 'Development',
                date: new DateTimeImmutable('today'),
                comment: 'Any work'
            )
        ];

        // Act
        $stream = $destination->logTimeEntries($entries);
        $events = iterator_to_array($stream);

        // Assert
        self::assertCount(count($entries), $events);
        foreach ($events as $event) {
            self::assertInstanceOf(DeliveryEvent::class, $event);
            self::assertTrue($event->success, $event->error?->getMessage() ?? '');
            self::assertNull($event->error);
        }
    }

    /**
     * Many concurrent requests must not fail by timeout while YouTrack processes them
     */
    public function testLogManyTimeEntriesToRealYouTrack(): void
    {
        // Arrange
        $destination = $this->createDestination();

        $entries = [];
        for ($i = 0; $i < 100; $i++) {
            $entries[] = new TimeEntry(
                taskId: 'DEMO-' . ($i % 19 + 1),
                duration: Duration::fromString('15m'),
                workType: 'Development',
                date: new DateTimeImmutable('today'),
                comment: "Bulk work $i"
            );
        }

        // Act
        $events = iterator_to_array($destination->logTimeEntries($entries));

        // Assert
        self::assertCount(count($entries), $events);
        foreach ($events as $event) {
            self::assertTrue($event->success, $event->error?->getMessage() ?? '');
        }
    }

    private function createDestination(): YouTrackDestination
    {
        $host = self::$youtrackStartedContainer->getHost();
        $port = self::$youtrackStartedContainer->getMappedPort(8080);

        return new YouTrackDestination(
            HttpClientBuilder::buildDefault(),
            "http://{$host}:{$port}",
            'perm-YWRtaW4=.NDEtMA==.ftTjeUcU3jtQZ0tYIq0PXteDQI19DD',
        );
    }

    public static function setUpBeforeClass(): void
    {
        $youtrackContainer = new GenericContainer(
            'ghcr.io/igancev/youtrack-image-for-ci/youtrack-image-for-ci:2026.1.12848'
        )
            ->withExposedPorts(8080)
            ->withWait(
                new WaitForHttp(8080)
                    ->withPath('/api/config')
                    ->withTimeout(3 * 60 * 1000) // 3 minutes
            );
        self::$youtrackStartedContainer = $youtrackContainer->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$youtrackStartedContainer->stop();
    }
}
