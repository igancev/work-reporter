<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination\YouTrack;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\TimeoutException;
use Amp\NullCancellation;
use Amp\Pipeline\Queue;
use Amp\Sync\LocalSemaphore;
use Igancev\WorkReporter\Destination\DeliveryEvent;
use Igancev\WorkReporter\Destination\DeliveryStream;
use Igancev\WorkReporter\Destination\Destination;
use Igancev\WorkReporter\Destination\DestinationException;
use Igancev\WorkReporter\Destination\PipelineDeliveryStream;
use Igancev\WorkReporter\TimeEntry;
use JsonException;
use RuntimeException;
use Throwable;

use function Amp\async;
use function Amp\Future\await;
use function Amp\Future\awaitAll;

use const JSON_THROW_ON_ERROR;

final class YouTrackDestination implements Destination
{
    private const float TCP_CONNECT_TIMEOUT_SEC = 5;
    /**
     * Upper bound for a hung request, not an SLA: the timer covers server processing time,
     * and YouTrack keeps processing a work item even after the client gave up on it.
     */
    private const float TIMEOUT_SEC = 30;
    /**
     * YouTrack processes work item creation almost sequentially, so flooding it with requests
     * only makes each of them wait longer in the server queue.
     */
    private const int MAX_CONCURRENT_REPORTS = 8;

    private string $baseUrl;
    /** @var array<non-empty-string, string> */
    private array $headers;

    public function __construct(
        private readonly DelegateHttpClient $httpClient,
        private readonly string $url,
        private readonly string $token,
    ) {
        $this->baseUrl = rtrim($this->url, '/') . '/api/';
        $this->headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @param iterable<TimeEntry> $timeEntries
     * @return DeliveryStream<DeliveryEvent>
     * @throws DestinationException
     */
    public function logTimeEntries(iterable $timeEntries): DeliveryStream
    {
        $timeEntries = iterator_to_array($timeEntries);
        $workItems = $this->buildWorkItems($timeEntries);

        /** @var Queue<DeliveryEvent> $queue */
        $queue = new Queue();

        async(function () use ($queue, $workItems, $timeEntries) {
            $semaphore = new LocalSemaphore(self::MAX_CONCURRENT_REPORTS);
            $futures = [];
            foreach ($workItems as $index => $workItem) {
                $futures[$index] = async(function () use ($queue, $workItem, $timeEntries, $index, $semaphore) {
                    // Measure the request itself, not the time spent waiting for a free slot
                    $lock = $semaphore->acquire();
                    $startTime = hrtime(true);
                    $error = null;
                    try {
                        $this->reportWorkItem($workItem);
                    } catch (Throwable $e) {
                        $error = $e;
                    } finally {
                        // Release before push: push waits for the consumer and must not hold the slot
                        $lock->release();
                    }
                    $durationMs = (hrtime(true) - $startTime) / 1_000_000;

                    $queue->push(new DeliveryEvent(
                        $timeEntries[$index],
                        $durationMs,
                        $error === null,
                        $error,
                    ));
                });
            }

            awaitAll($futures);
            $queue->complete();
        });

        return new PipelineDeliveryStream($queue->pipe());
    }

    /**
     * @param iterable<TimeEntry> $timeEntries
     * @return WorkItem[]
     * @throws DestinationException
     */
    private function buildWorkItems(iterable $timeEntries): array
    {
        $taskIds = array_map(static fn(TimeEntry $entry) => TaskId::fromString($entry->taskId), (array)$timeEntries);
        $projectsCollection = $this->fetchProjectsByTasks($taskIds);
        $workItemTypes = $this->fetchWorkItemTypes($projectsCollection);

        $workItems = [];
        foreach ($timeEntries as $timeEntry) {
            $project = $projectsCollection->projectByTaskId(TaskId::fromString($timeEntry->taskId));
            $workItemType = $workItemTypes->getByProjectAndName(
                $project,
                $timeEntry->workType,
            );
            $workItems[] = new WorkItem(
                TaskId::fromString($timeEntry->taskId),
                $timeEntry->date,
                $timeEntry->duration,
                $workItemType,
                $timeEntry->comment,
            );
        }

        return $workItems;
    }

    /**
     * Fetches projects by tasks concurrently
     * @param TaskId[] $taskIds
     * @throws DestinationException
     */
    private function fetchProjectsByTasks(array $taskIds): ProjectCollection
    {
        $taskIdsUniqueByProject = new TaskIdCollection($taskIds)->filterUniqueByProject()->toArray();

        $futures = [];
        foreach ($taskIdsUniqueByProject as $taskId) {
            $futures[] = async(function () use ($taskId): Response {
                $url = $this->baseUrl . sprintf('issues/%s?fields=project(id,name,shortName)', $taskId->toString());
                $request = $this->createRequest($url, 'GET');

                return $this->httpClient->request($request, new NullCancellation());
            });
        }

        $projects = [];

        try {
            /** @var Response[] $responses */
            $responses = await($futures);
            foreach ($responses as $response) {
                $body = $response->getBody()->buffer();

                // todo: make error catch: 404 etc.
                if ($response->getStatus() >= 400) {
                    $projects = implode(
                        ', ',
                        array_map(fn(TaskId $taskId) => $taskId->getProjectAlias(), $taskIdsUniqueByProject)
                    );
                    throw new DestinationException(
                        sprintf(
                            "Failed to fetch projects ($projects) from YouTrack:\n\n"
                            . "- HTTP status code: %d\n"
                            . "- Body: %s",
                            $response->getStatus(),
                            $body
                        ),
                        [
                            'httpStatusCode' => $response->getStatus(),
                            'body' => $body,
                        ]
                    );
                }

                $jsonData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $projects[] = new Project(
                    $jsonData['project']['id'] ??
                    throw new DestinationException('Project id is missing', ['jsonData' => $jsonData]),
                    $jsonData['project']['name'] ??
                    throw new DestinationException('Project name is missing', ['jsonData' => $jsonData]),
                    $jsonData['project']['shortName'] ??
                    throw new DestinationException('Project name is missing', ['jsonData' => $jsonData]),
                );
            }
        } catch (HttpException $e) {
            throw new DestinationException(
                'Failed to fetch projects from Youtrack: ' . $e->getMessage(),
                ['url' => $this->url],
                $e,
            );
        } catch (BufferException | StreamException $e) {
            throw new DestinationException('Buffers the entire message failed: ' . $e->getMessage(), [], $e);
        } catch (JsonException $e) {
            throw new DestinationException('Invalid json response', ['body' => $body], $e);
        }

        return new ProjectCollection($projects);
    }

    private function fetchWorkItemTypes(ProjectCollection $projectsCollection): WorkItemTypeCollection
    {
        $features = [];
        foreach ($projectsCollection->getProjects() as $project) {
            $url = $this->baseUrl . sprintf(
                'admin/projects/%s/timeTrackingSettings?top=-1&fields=workItemTypes(id,name)',
                $project->id
            );
            $request = $this->createRequest($url, 'GET');

            $features[] = async(function () use ($request, $project): array {
                try {
                    $response = $this->httpClient->request($request, new NullCancellation());
                    $body = $response->getBody()->buffer();

                    if ($response->getStatus() >= 400) {
                        throw new DestinationException('Failed to fetch WorkItemTypes from destination', [
                            'httpStatusCode' => $response->getStatus(),
                            'body' => $body,
                        ]);
                    }
                } catch (HttpException $e) {
                    throw new DestinationException(
                        'Failed to fetch WorkItemTypes from destination: ' . $e->getMessage(),
                        ['url' => $this->url],
                        $e,
                    );
                } catch (BufferException | StreamException $e) {
                    throw new DestinationException('Buffers the entire message failed: ' . $e->getMessage(), [], $e);
                }

                $jsonData = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

                $workItemTypes = [];
                foreach ($jsonData['workItemTypes'] as $workItemType) {
                    $workItemTypes[] = new WorkItemType(
                        $workItemType['id'],
                        $workItemType['name'],
                        $project->id,
                    );
                }

                return $workItemTypes;
            });
        }

        /** @var array<array-key, WorkItemType[]> $workItemTypes */
        $workItemTypes = await($features);

        return new WorkItemTypeCollection(array_merge(...$workItemTypes));
    }

    /**
     * @throws DestinationException
     */
    private function reportWorkItem(WorkItem $workItem): void
    {
        // todo: add normal error handling

        $body = [
            'duration' => [
                'presentation' => $workItem->duration->toString(),
            ],
            'date' => $workItem->date->getTimestamp() * 1000,
            'type' => [
                'id' => $workItem->workItemType->id,
            ],
            'text' => $workItem->comment,
        ];

        try {
            $url = $this->baseUrl . sprintf('issues/%s/timeTracking/workItems', $workItem->taskId->toString());
            $request = $this->createRequest($url, 'POST', json_encode($body, JSON_THROW_ON_ERROR));

            $response = $this->httpClient->request($request, new NullCancellation());

            if ($response->getStatus() >= 400) {
                $errorBody = $response->getBody()->buffer();
                throw new RuntimeException(sprintf('Failed to report time: %d %s', $response->getStatus(), $errorBody));
            }
        } catch (TimeoutException $e) {
            // The request may have reached YouTrack and be processed there after the client gave up
            throw new DestinationException(
                'Timeout, delivery status is unknown: check YouTrack before retrying. ' . $e->getMessage(),
                ['taskId' => $workItem->taskId->toString()],
                $e,
            );
        } catch (Throwable $e) {
            throw new DestinationException($e->getMessage(), [], $e);
        }
    }

    /**
     * @param non-empty-string $method
     */
    private function createRequest(string $url, string $method, string $body = ''): Request
    {
        $request = new Request($url, $method, $body);
        $request->setHeaders($this->headers);
        $request->setTcpConnectTimeout(self::TCP_CONNECT_TIMEOUT_SEC);
        $request->setTransferTimeout(self::TIMEOUT_SEC);
        // Default inactivity timeout (10 s) would otherwise cut off waiting for the response earlier
        $request->setInactivityTimeout(self::TIMEOUT_SEC);

        return $request;
    }
}
