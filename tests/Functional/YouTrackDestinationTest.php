<?php

declare(strict_types=1);

namespace Tests\Functional;

use Amp\Http\Client\HttpClientBuilder;
use DateTimeImmutable;
use Igancev\WorkReporter\Destination\YouTrack\YouTrackDestination;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\TimeEntry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Testcontainers\Container\GenericContainer;
use Testcontainers\Container\StartedTestContainer;
use Testcontainers\Wait\WaitForHttp;

#[Group('functional')]
class YouTrackDestinationTest extends TestCase
{
    private StartedTestContainer $youtrackStartedContainer;

    public function testLogTimeEntriesToRealYouTrack(): void
    {
        // Arrange
        // instantiate YouTrackDestination with testcontainers YouTrack
        $httpClient = HttpClientBuilder::buildDefault();
        $host = $this->youtrackStartedContainer->getHost();
        $port = $this->youtrackStartedContainer->getMappedPort(8080);
        $destination = new YouTrackDestination(
            $httpClient,
            "http://{$host}:{$port}",
            'perm-YWRtaW4=.NDEtMA==.ftTjeUcU3jtQZ0tYIq0PXteDQI19DD',
        );

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
        $result = $destination->logTimeEntries($entries);

        // Assert
        self::assertEmpty($result->failures());
        self::assertSame(count($entries), $result->successfulCount());
        self::assertSame(count($entries), count($result->successDelivered()));
    }

    protected function setUp(): void
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
        $this->youtrackStartedContainer = $youtrackContainer->start();
    }

    protected function tearDown(): void
    {
        $this->youtrackStartedContainer->stop();
    }
}
