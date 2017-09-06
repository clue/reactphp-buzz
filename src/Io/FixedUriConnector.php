<?php

namespace Clue\React\Buzz\Io;

use React\Socket\ConnectorInterface;

/** @internal */
class FixedUriConnector implements ConnectorInterface
{
    private $uri;
    private $connector;

    public function __construct($uri, ConnectorInterface $connector)
    {
        $this->uri = $uri;
        $this->connector = $connector;
    }

    public function connect($_)
    {
        return $this->connector->connect($this->uri);
    }
}
