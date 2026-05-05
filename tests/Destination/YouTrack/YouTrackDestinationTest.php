<?php

declare(strict_types=1);

namespace Tests\Destination\YouTrack;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\InvalidHeaderException;
use DateTimeImmutable;
use Igancev\WorkReporter\Destination\DestinationException;
use Igancev\WorkReporter\Destination\YouTrack\YouTrackDestination;
use Igancev\WorkReporter\Duration;
use Igancev\WorkReporter\TimeEntry;
use JsonException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(YouTrackDestination::class)]
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
        $result = $this->destination->logTimeEntries([$entry]);

        // Assert
        $this->assertTrue($result->isSuccessful());
        $this->assertSame(1, $result->successfulCount());
        $this->assertSame(0, $result->failuresCount());
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
        $this->expectExceptionMessage('Failed to fetch data from destination');

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
        $result = $this->destination->logTimeEntries([$entry]);

        // Assert
        $this->assertFalse($result->isSuccessful());
        $this->assertSame(0, $result->successfulCount());
        $this->assertSame(1, $result->failuresCount());
        $this->assertStringContainsString(
            'Failed to report time: 500',
            $result->failures()[0]->exception->getMessage()
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
        $this->expectExceptionMessage('Failed to fetch projects from destination: Network Error');

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
