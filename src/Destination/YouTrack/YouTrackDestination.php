<?php

namespace Igancev\WorkReporter\Destination\YouTrack;

use Amp\ByteStream\BufferException;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\NullCancellation;
use Igancev\WorkReporter\Destination\BatchDeliveryResult;
use Igancev\WorkReporter\Destination\DeliveryFailure;
use Igancev\WorkReporter\Destination\Destination;
use Igancev\WorkReporter\Destination\DestinationException;
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
    private const float TCP_CONNECT_TIMEOUT_SEC = 0.2;
    private const float TIMEOUT_SEC = 2;

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
     * @throws DestinationException
     */
    public function logTimeEntries(iterable $timeEntries): BatchDeliveryResult
    {
        $timeEntries = iterator_to_array($timeEntries);
        $workItems = $this->buildWorkItems($timeEntries);

        $futures = [];
        foreach ($workItems as $workItem) {
            $futures[] = async(fn() => $this->reportWorkItem($workItem));
        }

        /** @var WorkItem[] $successes */
        [$errors, $successes] = awaitAll($futures);

        $failures = [];
        foreach ($errors as $index => $error) {
            $failures[] = new DeliveryFailure($timeEntries[$index], $error);
        }

        $successDelivered = [];
        foreach ($successes as $index => $success) {
            $successDelivered[] = $timeEntries[$index];
        }

        return new BatchDeliveryResult($successDelivered, $failures);
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

        $features = [];
        foreach ($taskIdsUniqueByProject as $taskId) {
            $features[] = async(function () use ($taskId): Response {
                $url = $this->baseUrl . sprintf('issues/%s?fields=project(id,name,shortName)', $taskId->toString());
                $request = new Request($url, 'GET');
                $request->setHeaders($this->headers);
                $request->setTcpConnectTimeout(self::TCP_CONNECT_TIMEOUT_SEC);
                $request->setTransferTimeout(self::TIMEOUT_SEC);

                return $this->httpClient->request($request, new NullCancellation());
            });
        }

        $projects = [];

        try {
            /** @var Response[] $responses */
            $responses = await($features);
            foreach ($responses as $response) {
                $body = $response->getBody()->buffer();

                // todo: make error catch: 404 etc.
                if ($response->getStatus() >= 400) {
                    throw new DestinationException(
                        sprintf(
                            "Failed to fetch data from destination:\n\n"
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
                'Failed to fetch projects from destination: ' . $e->getMessage(),
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
            $request = new Request($url, 'GET');
            $request->setHeaders($this->headers);
            $request->setTcpConnectTimeout(self::TCP_CONNECT_TIMEOUT_SEC);
            $request->setTransferTimeout(self::TIMEOUT_SEC);

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
    private function reportWorkItem(WorkItem $workItem): WorkItem
    {
        // todo: добавить нормальную обработку ошибок

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
            $request = new Request($url, 'POST', json_encode($body, JSON_THROW_ON_ERROR));
            $request->setHeaders($this->headers);
            $request->setTcpConnectTimeout(self::TCP_CONNECT_TIMEOUT_SEC);
            $request->setTransferTimeout(self::TIMEOUT_SEC);

            $response = $this->httpClient->request($request, new NullCancellation());

            if ($response->getStatus() >= 400) {
                $errorBody = $response->getBody()->buffer();
                throw new RuntimeException(sprintf('Failed to report time: %d %s', $response->getStatus(), $errorBody));
            }

            return $workItem;
        } catch (Throwable $e) {
            throw new DestinationException(
                sprintf('API Error for task %s: %s', $workItem->taskId->toString(), $e->getMessage()),
                [],
                $e,
            );
        }
    }
}
