<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Destination;

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Socket\DnsSocketConnector;
use Igancev\WorkReporter\Config\ConfigProvider;
use Igancev\WorkReporter\Destination\YouTrack\YouTrackDestination;

final readonly class ConcreteDestinationFactory implements DestinationFactory
{
    public function __construct(
        private ConfigProvider $configProvider,
    ) {
    }

    public function build(DestinationType $destination): Destination
    {
        return match ($destination) {
            DestinationType::YouTrack => $this->buildYouTrackDestination(),
        };
    }

    /**
     * @throws DestinationException
     */
    private function buildYouTrackDestination(): YouTrackDestination
    {
        $youtrackConfigDestinations = $this->configProvider->getConfig()->destinations->youTrack;
        if ($youtrackConfigDestinations === null) {
            throw new DestinationException("Definition of YouTrack destination missing in configuration");
        }

        return new YouTrackDestination(
            new HttpClientBuilder()
                ->usingPool(
                    new UnlimitedConnectionPool(new DefaultConnectionFactory(new DnsSocketConnector()))
                )
                ->build(),
            $youtrackConfigDestinations->url,
            $youtrackConfigDestinations->token,
        );
    }
}
