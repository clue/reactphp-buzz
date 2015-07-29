<?php

namespace Clue\React\Buzz\Message;

use InvalidArgumentException;

class Uri
{
    private $scheme;
    private $host;
    private $path;
    private $query;

    public function __construct($uri)
    {
        $parts = parse_url($uri);
        if (!$parts || !isset($parts['scheme'], $parts['host'], $parts['path'])) {
            throw new InvalidArgumentException('Not a valid absolute URI');
        }

        $this->scheme = $parts['scheme'];
        $this->host = $parts['host'];
        $this->port = isset($parts['port']) ? $parts['port'] : null;
        $this->path = $parts['path'];
        $this->query = isset($parts['query']) ? $parts['query'] : '';
    }

    public function __toString()
    {
        $url = $this->scheme . '://' . $this->host;
        if ($this->port !== null) {
            $url .= ':' . $this->port;
        }
        $url .= $this->path;
        if ($this->query !== '') {
            $url .= '?' . $this->query;
        }

        return $url;
    }

    public function getScheme()
    {
        return $this->scheme;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getQuery()
    {
        return $this->query;
    }
}
