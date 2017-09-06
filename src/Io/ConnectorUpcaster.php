<?php

namespace Clue\React\Buzz\Io;

use React\Socket\ConnectorInterface;
use React\SocketClient\ConnectorInterface as LegacyConnectorInterface;
use React\Stream\DuplexStreamInterface;

/**
 * Adapter to upcast a legacy SocketClient:v0.7/v0.6 Connector to a new Socket:v0.8 Connector
 *
 * @internal
 */
class ConnectorUpcaster implements ConnectorInterface
{
    private $legacy;

    public function __construct(LegacyConnectorInterface $connector)
    {
        $this->legacy = $connector;
    }

    public function connect($uri)
    {
        return $this->legacy->connect($uri)->then(function (DuplexStreamInterface $stream) {
            return new ConnectionUpcaster($stream);
        });
    }
}
