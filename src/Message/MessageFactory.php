<?php

namespace Clue\React\Buzz\Message;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RingCentral\Psr7\Request;
use RingCentral\Psr7\Response;
use RingCentral\Psr7\Uri;
use React\Stream\ReadableStreamInterface;
use Clue\React\Zlib\ZlibFilterStream;

/**
 * @internal
 */
class MessageFactory
{
    /**
     * Creates an associative array of lowercase header names to the actual header
     *  casing.
     *
     * @param array $headers Associative array of HTML headers
     *
     * @return array
     */
    private static function _normalizeHeaderKeys(array $headers)
    {
        $result = array();

        foreach (array_keys($headers) as $key) {
            $result[strtolower($key)] = $key;
        }

        return $result;
    }

    /**
     * Automatically decode (decompress) responses when instructed.
     *
     * @param array                          $options Response options
     * @param array                          $headers Response headers
     * @param ReadableStreamInterface|string $body    Response body
     * 
     * @return ReadableStreamInterface|string
     */
    private static function _checkDecode(array $options, array &$headers, $body)
    {
        if (!empty($options['decodeContent'])
            && $body instanceof ReadableStreamInterface
            && extension_loaded('zlib')
        ) {
            $normalizedKeys = self::_normalizeHeaderKeys($headers);

            if (isset($normalizedKeys['content-encoding'])) {
                $encoding = $headers[$normalizedKeys['content-encoding']];

                if ($encoding === 'gzip' || $encoding === 'deflate') {
                    $body = $body->pipe(
                        $encoding === 'gzip'
                        ? ZlibFilterStream::createGzipDecompressor()
                        : ZlibFilterStream::createZlibDecompressor()
                    );

                    // Remove content-encoding header
                    $headers['x-encoded-content-encoding']
                        = $headers[$normalizedKeys['content-encoding']];
                    unset($headers[$normalizedKeys['content-encoding']]);

                    if (isset($normalizedKeys['content-length'])) {
                        // Remove content-length header
                        $headers['x-encoded-content-length']
                            = $headers[$normalizedKeys['content-length']];
                        unset($headers[$normalizedKeys['content-length']]);
                    }
                }
            }
        }

        return $body;
    }

    /**
     * Creates a new instance of RequestInterface for the given request parameters
     *
     * @param string                         $method
     * @param string|UriInterface            $uri
     * @param array                          $headers
     * @param string|ReadableStreamInterface $content
     * @return Request
     */
    public function request($method, $uri, $headers = array(), $content = '')
    {
        return new Request($method, $uri, $headers, $this->body($content), '1.0');
    }

    /**
     * Creates a new instance of ResponseInterface for the given response parameters
     *
     * @param string $version
     * @param int    $status
     * @param string $reason
     * @param array  $headers
     * @param ReadableStreamInterface|string $body
     * @param array $options Associative array containing the following options:
     *                       'decodeContent' => [bool]: whether response body
     *                       contents should be decoded (decompressed)
     * @return Response
     * @uses self::body()
     */
    public function response($version, $status, $reason, $headers = array(), $body = '', $options = array())
    {
        $body = self::_checkDecode($options, $headers, $body);

        return new Response($status, $headers, $this->body($body), $version, $reason);
    }

    /**
     * Creates a new instance of StreamInterface for the given body contents
     *
     * @param ReadableStreamInterface|string $body
     * @return StreamInterface
     */
    public function body($body)
    {
        if ($body instanceof ReadableStreamInterface) {
            return new ReadableBodyStream($body);
        }

        return \RingCentral\Psr7\stream_for($body);
    }

    /**
     * Creates a new instance of UriInterface for the given URI string
     *
     * @param string $uri
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
