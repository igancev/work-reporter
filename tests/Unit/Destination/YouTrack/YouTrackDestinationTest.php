<?php

declare(strict_types=1);

namespace Tests\Unit\Destination\YouTrack;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\Http\InvalidHeaderException;
use DateTimeImmutable;
use Igancev\WorkReporter\Destination\DeliveryEvent;
use Igancev\WorkReporter\Destination\DestinationException;
use Igancev\WorkReporter\Destination\YouTrack\WorkItem;
use Igancev\WorkReporter\Destination\YouTrack\YouTrackDestination;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\TimeEntry;
use JsonException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function Amp\delay;

#[CoversClass(YouTrackDestination::class)]
#[CoversClass(WorkItem::class)]
final class YouTrackDestinationTest extends TestCase
{
    private DelegateHttpClient&MockObject $httpClient;
    private YouTrackDestination $destination;

    /**
     * @throws DestinationException
     * @throws JsonException
     * @throws InvalidHeaderException
     */
    public function testSuccessfulDelivery(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        // 1. Mock Project fetch
        $projectResponse = $this->createResponse(200, [
            'project' => [
                'id' => 'p-1',
                'name' => 'Project 1',
                'shortName' => 'PROJ',
            ],
        ]);

        // 2. Mock WorkItemTypes fetch
        $typesResponse = $this->createResponse(200, [
            'workItemTypes' => [
                [
                    'id' => 't-1',
                    'name' => 'Development',
                ],
            ],
        ]);

        // 3. Mock WorkItem report (POST)
        $reportResponse = $this->createResponse(200, []);

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $projectResponse,
                $typesResponse,
                $reportResponse
            );

        // Act
        $stream = $this->destination->logTimeEntries([$entry]);
        $events = iterator_to_array($stream);

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(DeliveryEvent::class, $events[0]);
        $this->assertTrue($events[0]->success);
        $this->assertNull($events[0]->error);
    }

    private function createEntry(string $taskId, string $workType): TimeEntry
    {
        return new TimeEntry(
            $taskId,
            Duration::fromMinutes(60),
            $workType,
            new DateTimeImmutable('2026-04-09'),
            'Test comment'
        );
    }

    /**
     * @param array<string, mixed>|string $body
     * @throws InvalidHeaderException|JsonException
     */
    private function createResponse(int $status, array|string $body): Response
    {
        return new Response(
            '1.1',
            $status,
            'OK',
            [],
            is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body,
            new Request('https://example.com', 'GET')
        );
    }

    /**
     * @throws JsonException
     * @throws InvalidHeaderException
     */
    public function testFailsWhenProjectFetchFails(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $errorResponse = $this->createResponse(404, 'Not Found');

        $this->httpClient->expects($this->once())->method('request')->willReturn($errorResponse);

        // Assert
        $this->expectException(DestinationException::class);
        $this->expectExceptionMessage('Failed to fetch projects (PROJ) from YouTrack');

        // Act
        $this->destination->logTimeEntries([$entry]);
    }

    /**
     * @throws JsonException
     * @throws InvalidHeaderException
     */
    public function testFailsWhenWorkItemTypesFetchFails(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $projectResponse = $this->createResponse(200, [
            'project' => ['id' => 'p-1', 'name' => 'P1', 'shortName' => 'PROJ'],
        ]);

        $errorResponse = $this->createResponse(403, 'Forbidden');

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $projectResponse,
                $errorResponse
            );

        // Assert
        $this->expectException(DestinationException::class);
        $this->expectExceptionMessage('Failed to fetch WorkItemTypes from destination');

        // Act
        $this->destination->logTimeEntries([$entry]);
    }

    /**
     * @throws InvalidHeaderException
     * @throws JsonException
     * @throws DestinationException
     */
    public function testPartialFailureWhenReportingFails(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $projectResponse = $this->createResponse(200, [
            'project' => ['id' => 'p-1', 'name' => 'P1', 'shortName' => 'PROJ'],
        ]);

        $typesResponse = $this->createResponse(200, [
            'workItemTypes' => [['id' => 't-1', 'name' => 'Development']],
        ]);

        $errorResponse = $this->createResponse(500, 'Internal Error');

        $this->httpClient->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $projectResponse,
                $typesResponse,
                $errorResponse
            );

        // Act
        $stream = $this->destination->logTimeEntries([$entry]);
        $events = iterator_to_array($stream);

        // Assert
        $this->assertCount(1, $events);
        $this->assertInstanceOf(DeliveryEvent::class, $events[0]);
        $this->assertFalse($events[0]->success);
        $this->assertNotNull($events[0]->error);
        $this->assertStringContainsString(
            'Failed to report time: 500',
            $events[0]->error->getMessage()
        );
    }

    public function testFailsOnNetworkError(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $this->httpClient->expects($this->once())->method('request')
            ->willThrowException(new HttpException('Network Error'));

        // Assert
        $this->expectException(DestinationException::class);
        $this->expectExceptionMessage('Failed to fetch projects from Youtrack: Network Error');

        // Act
        $this->destination->logTimeEntries([$entry]);
    }

    /**
     * @throws JsonException
     * @throws InvalidHeaderException
     */
    public function testFailsOnInvalidJsonResponse(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $invalidResponse = $this->createResponse(200, '{ invalid json');

        $this->httpClient->expects($this->once())->method('request')->willReturn($invalidResponse);

        // Assert
        $this->expectException(DestinationException::class);
        $this->expectExceptionMessage('Invalid json response');

        // Act
        $this->destination->logTimeEntries([$entry]);
    }

    /**
     * @throws InvalidHeaderException
     */
    public function testFailsOnStreamBufferError(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $stream = $this->createMock(ReadableStream::class);
        $stream->expects($this->once())
            ->method('read')
            ->willThrowException(new BufferException('buffer', 'Buffer failed'));

        $response = new Response(
            '1.1',
            210,
            'OK',
            [],
            $stream,
            new Request('https://example.com', 'GET')
        );

        $this->httpClient->expects($this->once())->method('request')->willReturn($response);

        // Assert
        $this->expectException(DestinationException::class);
        $this->expectExceptionMessage('Buffers the entire message failed: Buffer failed');

        // Act
        $this->destination->logTimeEntries([$entry]);
    }

    /**
     * @throws InvalidHeaderException
     * @throws JsonException
     */
    public function testFailsWhenWorkItemTypesFetchFailsOnNetworkError(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $projectResponse = $this->createResponse(200, [
            'project' => ['id' => 'p-1', 'name' => 'P1', 'shortName' => 'PROJ'],
        ]);

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $projectResponse,
                $this->throwException(new HttpException('Network error during types fetch'))
            );

        // Assert
        $this->expectException(DestinationException::class);
        $this->expectExceptionMessage(
            'Failed to fetch WorkItemTypes from destination: Network error during types fetch'
        );

        // Act
        $this->destination->logTimeEntries([$entry]);
    }

    /**
     * @throws InvalidHeaderException
     * @throws JsonException
     */
    public function testFailsWhenWorkItemTypesFetchFailsOnStreamError(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $projectResponse = $this->createResponse(200, [
            'project' => ['id' => 'p-1', 'name' => 'P1', 'shortName' => 'PROJ'],
        ]);

        $stream = $this->createMock(ReadableStream::class);
        $stream->expects($this->once())
            ->method('read')
            ->willThrowException(new StreamException('Stream failed during types fetch'));

        $typesResponse = new Response(
            '1.1',
            200,
            'OK',
            [],
            $stream,
            new Request('https://example.com', 'GET')
        );

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $projectResponse,
                $typesResponse
            );

        // Assert
        $this->expectException(DestinationException::class);
        $this->expectExceptionMessage(
            'Buffers the entire message failed: Stream failed during types fetch'
        );

        // Act
        $this->destination->logTimeEntries([$entry]);
    }

    /**
     * @throws InvalidHeaderException
     * @throws JsonException
     * @throws DestinationException
     */
    public function testFailsWhenWorkItemTypeNotFoundInProject(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'UnknownType');

        $projectResponse = $this->createResponse(200, [
            'project' => ['id' => 'p-1', 'name' => 'Project 1', 'shortName' => 'PROJ'],
        ]);

        $typesResponse = $this->createResponse(200, [
            'workItemTypes' => [['id' => 't-1', 'name' => 'Development']],
        ]);

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $projectResponse,
                $typesResponse
            );

        // Assert
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('WorkItemType "UnknownType" not found in project "Project 1"');

        // Act
        $this->destination->logTimeEntries([$entry]);
    }

    /**
     * @throws DestinationException
     */
    public function testLimitsConcurrentReports(): void
    {
        // Arrange
        $entries = [];
        for ($i = 1; $i <= 20; $i++) {
            $entries[] = $this->createEntry('PROJ-' . $i, 'Development');
        }

        $activeReports = 0;
        $maxActiveReports = 0;
        $this->httpClient->expects($this->exactly(22))->method('request')->willReturnCallback(
            function (Request $request) use (&$activeReports, &$maxActiveReports): Response {
                if ($request->getMethod() === 'GET') {
                    return $this->createMetadataResponse($request);
                }

                $activeReports++;
                $maxActiveReports = max($maxActiveReports, $activeReports);
                delay(0.01);
                $activeReports--;

                return $this->createResponse(200, []);
            }
        );

        // Act
        $events = iterator_to_array($this->destination->logTimeEntries($entries));

        // Assert
        $this->assertCount(20, $events);
        foreach ($events as $event) {
            $this->assertTrue($event->success);
        }
        $this->assertSame(8, $maxActiveReports);
    }

    /**
     * @throws DestinationException
     */
    public function testSetsRequestTimeouts(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        /** @var Request[] $requests */
        $requests = [];
        $this->httpClient->expects($this->exactly(3))->method('request')->willReturnCallback(
            function (Request $request) use (&$requests): Response {
                $requests[] = $request;

                return $request->getMethod() === 'GET'
                    ? $this->createMetadataResponse($request)
                    : $this->createResponse(200, []);
            }
        );

        // Act
        iterator_to_array($this->destination->logTimeEntries([$entry]));

        // Assert
        $this->assertCount(3, $requests);
        foreach ($requests as $request) {
            $this->assertSame(5.0, $request->getTcpConnectTimeout());
            $this->assertSame(30.0, $request->getTransferTimeout());
            $this->assertSame(30.0, $request->getInactivityTimeout());
        }
    }

    /**
     * @throws DestinationException
     */
    public function testReportsTimeoutAsUnknownDeliveryStatus(): void
    {
        // Arrange
        $entry = $this->createEntry('PROJ-1', 'Development');

        $this->httpClient->expects($this->exactly(3))->method('request')->willReturnCallback(
            function (Request $request): Response {
                if ($request->getMethod() === 'GET') {
                    return $this->createMetadataResponse($request);
                }

                throw new TimeoutException('Allowed transfer timeout exceeded, took longer than 30 s');
            }
        );

        // Act
        $events = iterator_to_array($this->destination->logTimeEntries([$entry]));

        // Assert
        $this->assertCount(1, $events);
        $this->assertFalse($events[0]->success);
        $this->assertInstanceOf(DestinationException::class, $events[0]->error);
        $this->assertSame(
            'Timeout, delivery status is unknown: check YouTrack before retrying. '
            . 'Allowed transfer timeout exceeded, took longer than 30 s',
            $events[0]->error->getMessage()
        );
        $this->assertInstanceOf(TimeoutException::class, $events[0]->error->getPrevious());
    }

    /**
     * Responds to project and work item type requests of the PROJ project
     */
    private function createMetadataResponse(Request $request): Response
    {
        if (str_contains($request->getUri()->getPath(), 'timeTrackingSettings')) {
            return $this->createResponse(200, [
                'workItemTypes' => [['id' => 't-1', 'name' => 'Development']],
            ]);
        }

        return $this->createResponse(200, [
            'project' => ['id' => 'p-1', 'name' => 'Project 1', 'shortName' => 'PROJ'],
        ]);
    }

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(DelegateHttpClient::class);
        $this->destination = new YouTrackDestination(
            $this->httpClient,
            'https://example.youtrack.cloud',
            'perm:token'
        );
    }
}
