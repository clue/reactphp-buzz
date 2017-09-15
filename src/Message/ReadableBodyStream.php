<?php

namespace Clue\React\Buzz\Message;

use Evenement\EventEmitter;
use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

/**
 * @internal
 */
class ReadableBodyStream extends EventEmitter implements ReadableStreamInterface, StreamInterface
{
    private $input;
    private $closed = false;

    public function __construct(ReadableStreamInterface $input)
    {
        $this->input = $input;

        $that = $this;
        $input->on('data', function ($data) use ($that) {
            $that->emit('data', array($data));
        });
        $input->on('error', function ($error) use ($that) {
            $that->emit('error', array($error));
            $that->close();
        });
        $input->on('end', function () use ($that) {
            $that->emit('end');
            $that->close();
        });
        $input->on('close', array($that, 'close'));
    }

    public function close()
    {
        if (!$this->closed) {
            $this->closed = true;
            $this->input->close();

            $this->emit('close');
            $this->removeAllListeners();
        }
    }

    public function isReadable()
    {
        return $this->input->isReadable();
    }

    public function pause()
    {
        $this->input->pause();
    }

    public function resume()
    {
        $this->input->resume();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }

    public function eof()
    {
        return !$this->isReadable();
    }

    public function __toString()
    {
        return '';
    }

    public function detach()
    {
        throw new \BadMethodCallException();
    }

    public function getSize()
    {
        return null;
    }

    public function tell()
    {
        throw new \BadMethodCallException();
    }

    public function isSeekable()
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \BadMethodCallException();
    }

    public function rewind()
    {
        throw new \BadMethodCallException();
    }

    public function isWritable()
    {
        return false;
    }

    public function write($string)
    {
        throw new \BadMethodCallException();
    }

    public function read($length)
    {
        throw new \BadMethodCallException();
    }

    public function getContents()
    {
        throw new \BadMethodCallException();
    }

    public function getMetadata($key = null)
    {
        return ($key === null) ? array() : null;
    }
}
