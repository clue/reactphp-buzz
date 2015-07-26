<?php

namespace Clue\React\Buzz\Message;

use InvalidArgumentException;

class Uri
{
    private $scheme;
    private $host;
    private $port;
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

    /**
     * Reolves the given $uri by appending it behind $this base URI
     *
     * @param unknown $uri
     * @return Uri
     * @throws UnexpectedValueException
     * @internal
     * @see Browser::resolve()
     */
    public function expandBase($uri)
    {
        if ($uri instanceof self) {
            return $this->assertBase($uri);
        }

        try {
            return $this->assertBase(new self($uri));
        } catch (\InvalidArgumentException $e) {
            // not an absolute URI
        }

        $new = clone $this;

        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $new->query = substr($uri, $pos + 1);
            $uri = substr($uri, 0, $pos);
        }

        if ($uri !== '' && substr($new->path, -1) !== '/') {
            $new->path .= '/';
        }

        if (isset($uri[0]) && $uri[0] === '/') {
            $uri = substr($uri, 1);
        }

        $new->path .= $uri;

        return $new;
    }

    private function assertBase(Uri $new)
    {
        if ($new->scheme !== $this->scheme || $new->host !== $this->host || $new->port !== $this->port || strpos($new->path, $this->path) !== 0) {
            throw new \UnexpectedValueException('Invalid base, "' . $new . '" does not appear to be below "' . $this . '"');
        }

        return $new;
    }
}
