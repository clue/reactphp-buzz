<?php

namespace Clue\React\Buzz\Io;

use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use RuntimeException;

/**
 * dummy unix domain socket connector
 *
 * The path to connect to is set once during instantiation, the actual
 * target host is then ignored.
 *
 * Unix domain sockets use atomic operations, so we can as well emulate
 * async behavior.
 *
 * @internal
 */
class UnixConnector implements ConnectorInterface
{
    private $loop;
    private $path;

    public function __construct(LoopInterface $loop, $path)
    {
        if (substr($path, 0, 7) !== 'unix://') {
            $path = 'unix://' . $path;
        }

        $this->loop = $loop;
        $this->path = $path;
    }

    public function create($host, $port)
    {
        $deferred = new Deferred();

        $resource = @stream_socket_client($this->path, $errno, $errstr, 1.0);

        if (!$resource) {
            $deferred->reject(new RuntimeException('Unable to connect to unix domain socket path: ' . $errstr, $errno));
        } else {
            $deferred->resolve(new Stream($resource, $this->loop));
        }

        return $deferred->promise();
    }
}
