<?php

namespace Clue\React\Buzz\Io;

use Evenement\EventEmitter;
use React\Socket\ConnectionInterface;
use React\Stream\DuplexStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

/**
 * Adapter to upcast a legacy SocketClient:v0.5 Connector result to a new Socket:v0.8 ConnectionInterface
 *
 * @internal
 */
class ConnectionUpcaster extends EventEmitter implements ConnectionInterface
{
    private $stream;

    public function __construct(DuplexStreamInterface $stream)
    {
        $this->stream = $stream;

        Util::forwardEvents($stream, $this, array('data', 'end', 'close', 'error', 'drain'));
    }

    public function isReadable()
    {
        return $this->stream->isReadable();
    }

    public function isWritable()
    {
        return $this->isWritable();
    }

    public function pause()
    {
        $this->stream->pause();
    }

    public function resume()
    {
        $this->stream->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        $this->stream->pipe($dest, $options);
    }

    public function write($data)
    {
        return $this->stream->write($data);
    }

    public function end($data = null)
    {
        return $this->stream->end($data);
    }

    public function close()
    {
        $this->stream->close();
        $this->removeAllListeners();
    }

    public function getRemoteAddress()
    {
        return null;
    }

    public function getLocalAddress()
    {
        return null;
    }
}
