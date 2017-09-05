<?php

namespace Clue\React\Buzz\Io;

use React\Socket\ConnectorInterface;
use React\SocketClient\ConnectorInterface as LegacyConnectorInterface;
use React\Stream\Stream;

/**
 * Adapter to upcast a legacy SocketClient:v0.5 Connector to a new Socket:v0.8 Connector
 *
 * @internal
 */
class ConnectorUpcaster implements ConnectorInterface
{
    private $legacy;

    public function __construct(LegacyConnectorInterface$connector)
    {
        $this->legacy = $connector;
    }

    public function connect($uri)
    {
        $parts = parse_url((strpos($uri, '://') === false ? 'tcp://' : '') . $uri);
        if (!$parts || !isset($parts['host'], $parts['port'])) {
            return \React\Promise\reject(new \InvalidArgumentException('Unable to parse URI'));
        }

        return $this->legacy->create($parts['host'], $parts['port'])->then(function (Stream $stream) {
            return new ConnectionUpcaster($stream);
        });
    }
}
