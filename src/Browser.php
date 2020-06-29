<?php

namespace Clue\React\Buzz;

use Clue\React\Buzz\Io\Sender;
use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\MessageFactory;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectorInterface;
use React\Stream\ReadableStreamInterface;

class Browser
{
    private $transaction;
    private $messageFactory;
    private $baseUrl;
    private $protocolVersion = '1.1';

    /**
     * The `Browser` is responsible for sending HTTP requests to your HTTP server
     * and keeps track of pending incoming HTTP responses.
     * It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage).
     *
     * ```php
     * $loop = React\EventLoop\Factory::create();
     *
     * $browser = new Clue\React\Buzz\Browser($loop);
     * ```
     *
     * If you need custom connector settings (DNS resolution, TLS parameters, timeouts,
     * proxy servers etc.), you can explicitly pass a custom instance of the
     * [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface):
     *
     * ```php
     * $connector = new React\Socket\Connector($loop, array(
     *     'dns' => '127.0.0.1',
     *     'tcp' => array(
     *         'bindto' => '192.168.10.1:0'
     *     ),
     *     'tls' => array(
     *         'verify_peer' => false,
     *         'verify_peer_name' => false
     *     )
     * ));
     *
     * $browser = new Clue\React\Buzz\Browser($loop, $connector);
     * ```
     *
     * @param LoopInterface $loop
     * @param ConnectorInterface|null $connector [optional] Connector to use.
     *     Should be `null` in order to use default Connector.
     */
    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        $this->messageFactory = new MessageFactory();
        $this->transaction = new Transaction(
            Sender::createFromLoop($loop, $connector, $this->messageFactory),
            $this->messageFactory,
            $loop
        );
    }

    /**
     * Sends an HTTP GET request
     *
     * ```php
     * $browser->get($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
     *     var_dump((string)$response->getBody());
     * });
     * ```
     *
     * See also [example 01](../examples/01-google.php).
     *
     * @param string|UriInterface $url URL for the request.
     * @param array               $headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function get($url, array $headers = array())
    {
        return $this->requestMayBeStreaming('GET', $url, $headers);
    }

    /**
     * Sends an HTTP POST request
     *
     * ```php
     * $browser->post(
     *     $url,
     *     [
     *         'Content-Type' => 'application/json'
     *     ],
     *     json_encode($data)
     * )->then(function (Psr\Http\Message\ResponseInterface $response) {
     *     var_dump(json_decode((string)$response->getBody()));
     * });
     * ```
     *
     * See also [example 04](../examples/04-post-json.php).
     *
     * This method is also commonly used to submit HTML form data:
     *
     * ```php
     * $data = [
     *     'user' => 'Alice',
     *     'password' => 'secret'
     * ];
     *
     * $browser->post(
     *     $url,
     *     [
     *         'Content-Type' => 'application/x-www-form-urlencoded'
     *     ],
     *     http_build_query($data)
     * );
     * ```
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the outgoing request body is a `string`. If you're using a
     * streaming request body (`ReadableStreamInterface`), it will default to
     * using `Transfer-Encoding: chunked` or you have to explicitly pass in a
     * matching `Content-Length` request header like so:
     *
     * ```php
     * $body = new React\Stream\ThroughStream();
     * $loop->addTimer(1.0, function () use ($body) {
     *     $body->end("hello world");
     * });
     *
     * $browser->post($url, array('Content-Length' => '11'), $body);
     * ```
     *
     * @param string|UriInterface            $url     URL for the request.
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface<ResponseInterface>
     */
    public function post($url, array $headers = array(), $contents = '')
    {
        return $this->requestMayBeStreaming('POST', $url, $headers, $contents);
    }

    /**
     * Sends an HTTP HEAD request
     *
     * ```php
     * $browser->head($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
     *     var_dump($response->getHeaders());
     * });
     * ```
     *
     * @param string|UriInterface $url     URL for the request.
     * @param array               $headers
     * @return PromiseInterface<ResponseInterface>
     */
    public function head($url, array $headers = array())
    {
        return $this->requestMayBeStreaming('HEAD', $url, $headers);
    }

    /**
     * Sends an HTTP PATCH request
     *
     * ```php
     * $browser->patch(
     *     $url,
     *     [
     *         'Content-Type' => 'application/json'
     *     ],
     *     json_encode($data)
     * )->then(function (Psr\Http\Message\ResponseInterface $response) {
     *     var_dump(json_decode((string)$response->getBody()));
     * });
     * ```
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the outgoing request body is a `string`. If you're using a
     * streaming request body (`ReadableStreamInterface`), it will default to
     * using `Transfer-Encoding: chunked` or you have to explicitly pass in a
     * matching `Content-Length` request header like so:
     *
     * ```php
     * $body = new React\Stream\ThroughStream();
     * $loop->addTimer(1.0, function () use ($body) {
     *     $body->end("hello world");
     * });
     *
     * $browser->patch($url, array('Content-Length' => '11'), $body);
     * ```
     *
     * @param string|UriInterface            $url     URL for the request.
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface<ResponseInterface>
     */
    public function patch($url, array $headers = array(), $contents = '')
    {
        return $this->requestMayBeStreaming('PATCH', $url , $headers, $contents);
    }

    /**
     * Sends an HTTP PUT request
     *
     * ```php
     * $browser->put(
     *     $url,
     *     [
     *         'Content-Type' => 'text/xml'
     *     ],
     *     $xml->asXML()
     * )->then(function (Psr\Http\Message\ResponseInterface $response) {
     *     var_dump((string)$response->getBody());
     * });
     * ```
     *
     * See also [example 05](../examples/05-put-xml.php).
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the outgoing request body is a `string`. If you're using a
     * streaming request body (`ReadableStreamInterface`), it will default to
     * using `Transfer-Encoding: chunked` or you have to explicitly pass in a
     * matching `Content-Length` request header like so:
     *
     * ```php
     * $body = new React\Stream\ThroughStream();
     * $loop->addTimer(1.0, function () use ($body) {
     *     $body->end("hello world");
     * });
     *
     * $browser->put($url, array('Content-Length' => '11'), $body);
     * ```
     *
     * @param string|UriInterface            $url     URL for the request.
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface<ResponseInterface>
     */
    public function put($url, array $headers = array(), $contents = '')
    {
        return $this->requestMayBeStreaming('PUT', $url, $headers, $contents);
    }

    /**
     * Sends an HTTP DELETE request
     *
     * ```php
     * $browser->delete($url)->then(function (Psr\Http\Message\ResponseInterface $response) {
     *     var_dump((string)$response->getBody());
     * });
     * ```
     *
     * @param string|UriInterface            $url     URL for the request.
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface<ResponseInterface>
     */
    public function delete($url, array $headers = array(), $contents = '')
    {
        return $this->requestMayBeStreaming('DELETE', $url, $headers, $contents);
    }

    /**
     * Sends an arbitrary HTTP request.
     *
     * The preferred way to send an HTTP request is by using the above
     * [request methods](#request-methods), for example the [`get()`](#get)
     * method to send an HTTP `GET` request.
     *
     * As an alternative, if you want to use a custom HTTP request method, you
     * can use this method:
     *
     * ```php
     * $browser->request('OPTIONS', $url)->then(function (Psr\Http\Message\ResponseInterface $response) {
     *     var_dump((string)$response->getBody());
     * });
     * ```
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the size of the outgoing request body is known and non-empty.
     * For an empty request body, if will only include a `Content-Length: 0`
     * request header if the request method usually expects a request body (only
     * applies to `POST`, `PUT` and `PATCH`).
     *
     * If you're using a streaming request body (`ReadableStreamInterface`), it
     * will default to using `Transfer-Encoding: chunked` or you have to
     * explicitly pass in a matching `Content-Length` request header like so:
     *
     * ```php
     * $body = new React\Stream\ThroughStream();
     * $loop->addTimer(1.0, function () use ($body) {
     *     $body->end("hello world");
     * });
     *
     * $browser->request('POST', $url, array('Content-Length' => '11'), $body);
     * ```
     *
     * > Note that this method is available as of v2.9.0 and always buffers the
     *   response body before resolving.
     *   It does not respect the deprecated [`streaming` option](#withoptions).
     *   If you want to stream the response body, you can use the
     *   [`requestStreaming()`](#requeststreaming) method instead.
     *
     * @param string                         $method   HTTP request method, e.g. GET/HEAD/POST etc.
     * @param string                         $url      URL for the request
     * @param array                          $headers  Additional request headers
     * @param string|ReadableStreamInterface $body     HTTP request body contents
     * @return PromiseInterface<ResponseInterface,Exception>
     * @since 2.9.0
     */
    public function request($method, $url, array $headers = array(), $body = '')
    {
        return $this->withOptions(array('streaming' => false))->requestMayBeStreaming($method, $url, $headers, $body);
    }

    /**
     * Sends an arbitrary HTTP request and receives a streaming response without buffering the response body.
     *
     * The preferred way to send an HTTP request is by using the above
     * [request methods](#request-methods), for example the [`get()`](#get)
     * method to send an HTTP `GET` request. Each of these methods will buffer
     * the whole response body in memory by default. This is easy to get started
     * and works reasonably well for smaller responses.
     *
     * In some situations, it's a better idea to use a streaming approach, where
     * only small chunks have to be kept in memory. You can use this method to
     * send an arbitrary HTTP request and receive a streaming response. It uses
     * the same HTTP message API, but does not buffer the response body in
     * memory. It only processes the response body in small chunks as data is
     * received and forwards this data through [ReactPHP's Stream API](https://github.com/reactphp/stream).
     * This works for (any number of) responses of arbitrary sizes.
     *
     * ```php
     * $browser->requestStreaming('GET', $url)->then(function (Psr\Http\Message\ResponseInterface $response) {
     *     $body = $response->getBody();
     *     assert($body instanceof Psr\Http\Message\StreamInterface);
     *     assert($body instanceof React\Stream\ReadableStreamInterface);
     *
     *     $body->on('data', function ($chunk) {
     *         echo $chunk;
     *     });
     *
     *     $body->on('error', function (Exception $error) {
     *         echo 'Error: ' . $error->getMessage() . PHP_EOL;
     *     });
     *
     *     $body->on('close', function () {
     *         echo '[DONE]' . PHP_EOL;
     *     });
     * });
     * ```
     *
     * See also [`ReadableStreamInterface`](https://github.com/reactphp/stream#readablestreaminterface)
     * and the [streaming response](#streaming-response) for more details,
     * examples and possible use-cases.
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the size of the outgoing request body is known and non-empty.
     * For an empty request body, if will only include a `Content-Length: 0`
     * request header if the request method usually expects a request body (only
     * applies to `POST`, `PUT` and `PATCH`).
     *
     * If you're using a streaming request body (`ReadableStreamInterface`), it
     * will default to using `Transfer-Encoding: chunked` or you have to
     * explicitly pass in a matching `Content-Length` request header like so:
     *
     * ```php
     * $body = new React\Stream\ThroughStream();
     * $loop->addTimer(1.0, function () use ($body) {
     *     $body->end("hello world");
     * });
     *
     * $browser->requestStreaming('POST', $url, array('Content-Length' => '11'), $body);
     * ```
     *
     * > Note that this method is available as of v2.9.0 and always resolves the
     *   response without buffering the response body.
     *   It does not respect the deprecated [`streaming` option](#withoptions).
     *   If you want to buffer the response body, use can use the
     *   [`request()`](#request) method instead.
     *
     * @param string                         $method   HTTP request method, e.g. GET/HEAD/POST etc.
     * @param string                         $url      URL for the request
     * @param array                          $headers  Additional request headers
     * @param string|ReadableStreamInterface $body     HTTP request body contents
     * @return PromiseInterface<ResponseInterface,Exception>
     * @since 2.9.0
     */
    public function requestStreaming($method, $url, $headers = array(), $contents = '')
    {
        return $this->withOptions(array('streaming' => true))->requestMayBeStreaming($method, $url, $headers, $contents);
    }

    /**
     * [Deprecated] Submits an array of field values similar to submitting a form (`application/x-www-form-urlencoded`).
     *
     * ```php
     * // deprecated: see post() instead
     * $browser->submit($url, array('user' => 'test', 'password' => 'secret'));
     * ```
     *
     * This method will automatically add a matching `Content-Length` request
     * header for the encoded length of the given `$fields`.
     *
     * @param string|UriInterface $url     URL for the request.
     * @param array               $fields
     * @param array               $headers
     * @param string              $method
     * @return PromiseInterface<ResponseInterface>
     * @deprecated 2.9.0 See self::post() instead.
     * @see self::post()
     */
    public function submit($url, array $fields, $headers = array(), $method = 'POST')
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $contents = http_build_query($fields);

        return $this->requestMayBeStreaming($method, $url, $headers, $contents);
    }

    /**
     * [Deprecated] Sends an arbitrary instance implementing the [`RequestInterface`](#requestinterface) (PSR-7).
     *
     * The preferred way to send an HTTP request is by using the above
     * [request methods](#request-methods), for example the [`get()`](#get)
     * method to send an HTTP `GET` request.
     *
     * As an alternative, if you want to use a custom HTTP request method, you
     * can use this method:
     *
     * ```php
     * $request = new Request('OPTIONS', $url);
     *
     * // deprecated: see request() instead
     * $browser->send($request)->then(…);
     * ```
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the size of the outgoing request body is known and non-empty.
     * For an empty request body, if will only include a `Content-Length: 0`
     * request header if the request method usually expects a request body (only
     * applies to `POST`, `PUT` and `PATCH`).
     *
     * @param RequestInterface $request
     * @return PromiseInterface<ResponseInterface>
     * @deprecated 2.9.0 See self::request() instead.
     * @see self::request()
     */
    public function send(RequestInterface $request)
    {
        if ($this->baseUrl !== null) {
            // ensure we're actually below the base URL
            $request = $request->withUri($this->messageFactory->expandBase($request->getUri(), $this->baseUrl));
        }

        return $this->transaction->send($request);
    }

    /**
     * Changes the base URL used to resolve relative URLs to.
     *
     * ```php
     * $newBrowser = $browser->withBase('http://api.example.com/v3');
     * ```
     *
     * Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withBase()` method
     * actually returns a *new* [`Browser`](#browser) instance with the given base URL applied.
     *
     * Any requests to relative URLs will then be processed by first prepending
     * the (absolute) base URL.
     * Please note that this merely prepends the base URL and does *not* resolve
     * any relative path references (like `../` etc.).
     * This is mostly useful for (RESTful) API calls where all endpoints (URLs)
     * are located under a common base URL scheme.
     *
     * ```php
     * // will request http://api.example.com/v3/example
     * $newBrowser->get('/example')->then(…);
     * ```
     *
     * By definition of this library, a given base URL MUST always absolute and
     * can not contain any placeholders.
     *
     * @param string|UriInterface $baseUrl absolute base URL
     * @return self
     * @throws InvalidArgumentException if the given $baseUri is not a valid absolute URL
     * @see self::withoutBase()
     */
    public function withBase($baseUrl)
    {
        $browser = clone $this;
        $browser->baseUrl = $this->messageFactory->uri($baseUrl);

        if ($browser->baseUrl->getScheme() === '' || $browser->baseUrl->getHost() === '') {
            throw new \InvalidArgumentException('Base URL must be absolute');
        }

        return $browser;
    }

    /**
     * Removes the base URL.
     *
     * ```php
     * $newBrowser = $browser->withoutBase();
     * ```
     *
     * Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withoutBase()` method
     * actually returns a *new* [`Browser`](#browser) instance without any base URL applied.
     *
     * See also [`withBase()`](#withbase).
     *
     * @return self
     * @see self::withBase()
     */
    public function withoutBase()
    {
        $browser = clone $this;
        $browser->baseUrl = null;

        return $browser;
    }

    /**
     * Changes the [options](#options) to use:
     *
     * The [`Browser`](#browser) class exposes several options for the handling of
     * HTTP transactions. These options resemble some of PHP's
     * [HTTP context options](http://php.net/manual/en/context.http.php) and
     * can be controlled via the following API (and their defaults):
     *
     * ```php
     * $newBrowser = $browser->withOptions(array(
     *     'timeout' => null,
     *     'followRedirects' => true,
     *     'maxRedirects' => 10,
     *     'obeySuccessCode' => true,
     *     'streaming' => false, // deprecated, see requestStreaming() instead
     * ));
     * ```
     *
     * See also [timeouts](#timeouts), [redirects](#redirects) and
     * [streaming](#streaming) for more details.
     *
     * Notice that the [`Browser`](#browser) is an immutable object, i.e. this
     * method actually returns a *new* [`Browser`](#browser) instance with the
     * options applied.
     *
     * @param array $options
     * @return self
     */
    public function withOptions(array $options)
    {
        $browser = clone $this;
        $browser->transaction = $this->transaction->withOptions($options);

        return $browser;
    }

    /**
     * Changes the HTTP protocol version that will be used for all subsequent requests.
     *
     * All the above [request methods](#request-methods) default to sending
     * requests as HTTP/1.1. This is the preferred HTTP protocol version which
     * also provides decent backwards-compatibility with legacy HTTP/1.0
     * servers. As such, there should rarely be a need to explicitly change this
     * protocol version.
     *
     * If you want to explicitly use the legacy HTTP/1.0 protocol version, you
     * can use this method:
     *
     * ```php
     * $newBrowser = $browser->withProtocolVersion('1.0');
     *
     * $newBrowser->get($url)->then(…);
     * ```
     *
     * Notice that the [`Browser`](#browser) is an immutable object, i.e. this
     * method actually returns a *new* [`Browser`](#browser) instance with the
     * new protocol version applied.
     *
     * @param string $protocolVersion HTTP protocol version to use, must be one of "1.1" or "1.0"
     * @return self
     * @throws InvalidArgumentException
     * @since 2.8.0
     */
    public function withProtocolVersion($protocolVersion)
    {
        if (!\in_array($protocolVersion, array('1.0', '1.1'), true)) {
            throw new InvalidArgumentException('Invalid HTTP protocol version, must be one of "1.1" or "1.0"');
        }

        $browser = clone $this;
        $browser->protocolVersion = (string) $protocolVersion;

        return $browser;
    }

    /**
     * @param string                         $method
     * @param string|UriInterface            $url
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface<ResponseInterface,Exception>
     */
    private function requestMayBeStreaming($method, $url, array $headers = array(), $contents = '')
    {
        return $this->send($this->messageFactory->request($method, $url, $headers, $contents, $this->protocolVersion));
    }
}
