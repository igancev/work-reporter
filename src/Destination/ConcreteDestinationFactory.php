<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Socket\DnsSocketConnector;
use Igancev\WorkReporter\Destination\YouTrack\YouTrackDestination;
use InvalidArgumentException;

final readonly class ConcreteDestinationFactory implements DestinationFactory
{
    public function build(DestinationType $destination): Destination
    {
        return match ($destination) {
            DestinationType::YouTrack => $this->buildYouTrackDestination(),
        };
    }

    private function buildYouTrackDestination(): YouTrackDestination
    {
        // todo: load from config
        $youtrackUrl = 'http://localhost:8080';
        $youtrackToken = 'perm-tmpHardcodeToken';

        return new YouTrackDestination(
            new HttpClientBuilder()
                ->usingPool(
                    new UnlimitedConnectionPool(new DefaultConnectionFactory(new DnsSocketConnector()))
                )
                ->build(),
            $youtrackUrl,
            $youtrackToken,
        );
    }
}
