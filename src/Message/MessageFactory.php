<?php

namespace Clue\React\Buzz\Message;

use RingCentral\Psr7\Request;
use RingCentral\Psr7\Uri;
use RingCentral\Psr7\Response;
use Psr\Http\Message\UriInterface;
use RingCentral;

/**
 * @internal
 */
class MessageFactory
{
    /**
     * Creates a new instance of RequestInterface for the given request parameters
     *
     * @param string              $method
     * @param string|UriInterface $uri
     * @param array               $headers
     * @param string              $content
     * @return RequestInterface
     */
    public function request($method, $uri, $headers = array(), $content = '')
    {
        return new Request($method, $uri, $headers, $content);
    }

    /**
     * Creates a new instance of ResponseInterface for the given response parameters
     *
     * @param string $version
     * @param int    $status
     * @param string $reason
     * @param array  $headers
     * @param string $body
     * @return ResponseInterface
     */
    public function response($version, $status, $reason, $headers = array(), $body = '')
    {
        return new Response($status, $headers, $body, $version, $reason);
    }

    /**
     * Creates a new instance of StreamInterface for the given body contents
     *
     * @param string $body
     * @return StreamInterface
     */
    public function body($body)
    {
        return RingCentral\Psr7\stream_for($body);
    }

    /**
     * Creates a new instance of UriInterface for the given URI string or instance
     *
     * @param UriInterface|string $uri
     * @return UriInterface
     */
    public function uri($uri)
    {
        return new Uri($uri);
    }

    /**
     * Creates a new instance of UriInterface for the given URI string relative to the given base URI
     *
     * @param UriInterface $base
     * @param string       $uri
     * @return UriInterface
     */
    public function uriRelative(UriInterface $base, $uri)
    {
        return Uri::resolve($base, $uri);
    }

    /**
     * Resolves the given relative or absolute $uri by appending it behind $this base URI
     *
     * The given $uri parameter can be either a relative or absolute URI and
     * as such can not contain any URI template placeholders.
     *
     * As such, the outcome of this method represents a valid, absolute URI
     * which will be returned as an instance implementing `UriInterface`.
     *
     * If the given $uri is a relative URI, it will simply be appended behind $base URI.
     *
     * If the given $uri is an absolute URI, it will simply be verified to
     * be *below* the given $base URI.
     *
     * @param UriInterface $uri
     * @param UriInterface $base
     * @return UriInterface
     * @throws \UnexpectedValueException
     * @see Browser::resolve()
     */
    public function expandBase(UriInterface $uri, UriInterface $base)
    {
        if ($uri->getScheme() !== '') {
            if (strpos((string)$uri, (string)$base) !== 0) {
                throw new \UnexpectedValueException('Invalid base, "' . $uri . '" does not appear to be below "' . $base . '"');
            }
            return $uri;
        }

        $uri = (string)$uri;
        $base = (string)$base;

        if ($uri !== '' && substr($base, -1) !== '/' && substr($uri, 0, 1) !== '?') {
            $base .= '/';
        }

        if (isset($uri[0]) && $uri[0] === '/') {
            $uri = substr($uri, 1);
        }

        return $this->uri($base . $uri);
    }
}
