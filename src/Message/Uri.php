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

        if (strpos($uri, '{') !== false) {
            throw new \InvalidArgumentException('Contains placeholders');
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
     * Resolves the given relative or absolute $uri by appending it behind $this base URI
     *
     * The given $uri parameter can be either a relative or absolute URI string
     * which can optionally contain URI template placeholders.
     *
     * As such, its value or the outcome of this method does not neccessarily
     * have to represent a valid, absolute URI. Hence, it will be returned as
     * a string value instead of an `Uri` instance.
     *
     * If the given $uri is a relative URI, it will simply be appended behind $this base URI.
     *
     * If the given $uri is an absolute URI, it will simply be returned,
     * irrespective of the current base URI.
     *
     * @param string $uri
     * @return string
     * @internal
     * @see Browser::resolve()
     */
    public function expandBase($uri)
    {
        if (strpos($uri, '://') !== false) {
            return $uri;
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

        return (string)$new;
    }

    /**
     * Asserts that $this base URI is the base of the given $new Uri instance, i.e. the given $new URI is *below* this base URI
     *
     * @param Uri $new
     * @throws \UnexpectedValueException
     * @return Uri
     * @internal
     * @see Browser::resolve()
     */
    public function assertBaseOf(Uri $new)
    {
        if ($new->scheme !== $this->scheme || $new->host !== $this->host || $new->port !== $this->port || strpos($new->path, $this->path) !== 0) {
            throw new \UnexpectedValueException('Invalid base, "' . $new . '" does not appear to be below "' . $this . '"');
        }

        return $new;
    }
}
