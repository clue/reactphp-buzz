<?php

namespace Clue\React\Buzz\Message;

use InvalidArgumentException;
use ML\IRI\IRI;

/**
 * An `Uri` represents an absolute URI (aka URL).
 *
 * By definition of this library, an `Uri` instance is always absolute and can not contain any placeholders.
 */
class Uri
{
    private $iri;

    /**
     * Instantiate new absolute URI instance
     *
     * By definition of this library, an `Uri` instance is always absolute and can not contain any placeholders.
     * As such, any incomplete/relative URI will be rejected with an `InvalidArgumentException`.
     *
     * @param string|Uri|IRI $uri
     * @throws InvalidArgumentException for incomplete/relative URIs
     */
    public function __construct($uri)
    {
        if (!$uri instanceof IRI) {
            $uri = new IRI($uri);
        }

        if (!$uri->isAbsolute() || $uri->getHost() === '' || $uri->getPath() === '') {
            throw new InvalidArgumentException('Not a valid absolute URI');
        }

        if (strpos($uri, '{') !== false) {
            throw new InvalidArgumentException('Contains placeholders');
        }

        $this->iri = $uri;
    }

    public function __toString()
    {
        return (string)$this->iri;
    }

    public function getScheme()
    {
        return $this->iri->getScheme();
    }

    public function getHost()
    {
        return $this->iri->getHost();
    }

    public function getPort()
    {
        return $this->iri->getPort();
    }

    public function getPath()
    {
        return $this->iri->getPath();
    }

    public function getQuery()
    {
        return $this->iri->getQuery();
    }

    /**
     * Resolve a (relative) URI reference against this URI
     *
     * @param string|Uri $uri relative or absolute URI
     * @return Uri absolute URI
     * @link http://tools.ietf.org/html/rfc3986#section-5.2
     * @uses IRI::resolve()
     */
    public function resolve($uri)
    {
        if ($uri instanceof self) {
            $uri = (string)$uri;
        }
        return new self($this->iri->resolve($uri));
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

        $base = (string)$this;

        if ($uri !== '' && substr($base, -1) !== '/' && substr($uri, 0, 1) !== '?') {
            $base .= '/';
        }

        if (isset($uri[0]) && $uri[0] === '/') {
            $uri = substr($uri, 1);
        }

        return $base . $uri;
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
        if (strpos((string)$new, (string)$this) !== 0) {
            throw new \UnexpectedValueException('Invalid base, "' . $new . '" does not appear to be below "' . $this . '"');
        }

        return $new;
    }
}
