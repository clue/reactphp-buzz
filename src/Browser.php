<?php

namespace Clue\React\Buzz;

use Clue\React\Buzz\Io\Sender;
use Clue\React\Buzz\Io\Transaction;
use Clue\React\Buzz\Message\MessageFactory;
use Clue\React\Buzz\Middleware\MiddlewareInterface;
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
    private $baseUri = null;

    /** @var LoopInterface $loop */
    private $loop;

    /** @var MiddlewareInterface[] */
    private $middleware = array();

    /**
     * The `Browser` is responsible for sending HTTP requests to your HTTP server
     * and keeps track of pending incoming HTTP responses.
     * It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage).
     *
     * ```php
     * $loop = React\EventLoop\Factory::create();
     *
     * $browser = new Browser($loop);
     * ```
     *
     * If you need custom connector settings (DNS resolution, TLS parameters, timeouts,
     * proxy servers etc.), you can explicitly pass a custom instance of the
     * [`ConnectorInterface`](https://github.com/reactphp/socket#connectorinterface):
     *
     * ```php
     * $connector = new \React\Socket\Connector($loop, array(
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
     * $browser = new Browser($loop, $connector);
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
     * @param string|UriInterface $url URI for the request.
     * @param array               $headers
     * @return PromiseInterface
     */
    public function get($url, array $headers = array())
    {
        return $this->send($this->messageFactory->request('GET', $url, $headers));
    }

    /**
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the outgoing request body is a `string`. If you're using a
     * streaming request body (`ReadableStreamInterface`), it will default to
     * using `Transfer-Encoding: chunked` or you have to explicitly pass in a
     * matching `Content-Length` request header like so:
     *
     * ```php
     * $body = new ThroughStream();
     * $loop->addTimer(1.0, function () use ($body) {
     *     $body->end("hello world");
     * });
     *
     * $browser->post($url, array('Content-Length' => '11'), $body);
     * ```
     *
     * @param string|UriInterface            $url     URI for the request.
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface
     */
    public function post($url, array $headers = array(), $contents = '')
    {
        return $this->send($this->messageFactory->request('POST', $url, $headers, $contents));
    }

    /**
     * @param string|UriInterface $url     URI for the request.
     * @param array               $headers
     * @return PromiseInterface
     */
    public function head($url, array $headers = array())
    {
        return $this->send($this->messageFactory->request('HEAD', $url, $headers));
    }

    /**
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the outgoing request body is a `string`. If you're using a
     * streaming request body (`ReadableStreamInterface`), it will default to
     * using `Transfer-Encoding: chunked` or you have to explicitly pass in a
     * matching `Content-Length` request header like so:
     *
     * ```php
     * $body = new ThroughStream();
     * $loop->addTimer(1.0, function () use ($body) {
     *     $body->end("hello world");
     * });
     *
     * $browser->patch($url, array('Content-Length' => '11'), $body);
     * ```
     *
     * @param string|UriInterface            $url     URI for the request.
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface
     */
    public function patch($url, array $headers = array(), $contents = '')
    {
        return $this->send($this->messageFactory->request('PATCH', $url , $headers, $contents));
    }

    /**
     *
     * This method will automatically add a matching `Content-Length` request
     * header if the outgoing request body is a `string`. If you're using a
     * streaming request body (`ReadableStreamInterface`), it will default to
     * using `Transfer-Encoding: chunked` or you have to explicitly pass in a
     * matching `Content-Length` request header like so:
     *
     * ```php
     * $body = new ThroughStream();
     * $loop->addTimer(1.0, function () use ($body) {
     *     $body->end("hello world");
     * });
     *
     * $browser->put($url, array('Content-Length' => '11'), $body);
     * ```
     *
     * @param string|UriInterface            $url     URI for the request.
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface
     */
    public function put($url, array $headers = array(), $contents = '')
    {
        return $this->send($this->messageFactory->request('PUT', $url, $headers, $contents));
    }

    /**
     * @param string|UriInterface            $url     URI for the request.
     * @param array                          $headers
     * @param string|ReadableStreamInterface $contents
     * @return PromiseInterface
     */
    public function delete($url, array $headers = array(), $contents = '')
    {
        return $this->send($this->messageFactory->request('DELETE', $url, $headers, $contents));
    }

    /**
     * Submits an array of field values similar to submitting a form (`application/x-www-form-urlencoded`).
     *
     * ```php
     * $browser->submit($url, array('user' => 'test', 'password' => 'secret'));
     * ```
     *
     * This method will automatically add a matching `Content-Length` request
     * header for the encoded length of the given `$fields`.
     *
     * @param string|UriInterface $url     URI for the request.
     * @param array               $fields
     * @param array               $headers
     * @param string              $method
     * @return PromiseInterface
     */
    public function submit($url, array $fields, $headers = array(), $method = 'POST')
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $contents = http_build_query($fields);

        return $this->send($this->messageFactory->request($method, $url, $headers, $contents));
    }

    /**
     * Sends an arbitrary instance implementing the [`RequestInterface`](#requestinterface) (PSR-7).
     *
     * All the above [predefined methods](#methods) default to sending requests as HTTP/1.0.
     * If you need a custom HTTP protocol method or version, then you may want to use this
     * method:
     *
     * ```php
     * $request = new Request('OPTIONS', $url);
     * $request = $request->withProtocolVersion('1.1');
     *
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
     * @return PromiseInterface
     */
    public function send(RequestInterface $request)
    {
        if ($this->baseUri !== null) {
            // ensure we're actually below the base URI
            $request = $request->withUri($this->messageFactory->expandBase($request->getUri(), $this->baseUri));
        }

        $self = $this;
        $chain = $this->createMiddlewareChain($this->middleware, function (RequestInterface $request) use ($self) {
            return $self->transaction->send($request);
        });

        // Call the chain
        return $chain($request);
    }

    /**
     * Creates the middleware chain.
     *
     * @param MiddlewareInterface[] $middleware
     * @param callable $requestChainLast
     *
     * @return callable
     */
    private function createMiddlewareChain(array $middleware, callable $requestChainLast)
    {
        $middleware = array_reverse($middleware);

        // Build request chain
        $requestChainNext = $requestChainLast;
        foreach ($middleware as $m) {
            $lastCallable = static function (RequestInterface $request) use ($m, $requestChainNext) {
                return $m->handleRequest($request, $requestChainNext);
            };

            $requestChainNext = $lastCallable;
        }

        return $requestChainNext;
    }

    /**
     * Changes the base URI used to resolve relative URIs to.
     *
     * ```php
     * $newBrowser = $browser->withBase('http://api.example.com/v3');
     * ```
     *
     * Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withBase()` method
     * actually returns a *new* [`Browser`](#browser) instance with the given base URI applied.
     *
     * Any requests to relative URIs will then be processed by first prepending
     * the (absolute) base URI.
     * Please note that this merely prepends the base URI and does *not* resolve
     * any relative path references (like `../` etc.).
     * This is mostly useful for (RESTful) API calls where all endpoints (URIs)
     * are located under a common base URI scheme.
     *
     * ```php
     * // will request http://api.example.com/v3/example
     * $newBrowser->get('/example')->then(…);
     * ```
     *
     * By definition of this library, a given base URI MUST always absolute and
     * can not contain any placeholders.
     *
     * @param string|UriInterface $baseUri absolute base URI
     * @return self
     * @throws InvalidArgumentException if the given $baseUri is not a valid absolute URI
     * @see self::withoutBase()
     */
    public function withBase($baseUri)
    {
        $browser = clone $this;
        $browser->baseUri = $this->messageFactory->uri($baseUri);

        if ($browser->baseUri->getScheme() === '' || $browser->baseUri->getHost() === '') {
            throw new \InvalidArgumentException('Base URI must be absolute');
        }

        return $browser;
    }

    /**
     * Removes the base URI.
     *
     * ```php
     * $newBrowser = $browser->withoutBase();
     * ```
     *
     * Notice that the [`Browser`](#browser) is an immutable object, i.e. the `withoutBase()` method
     * actually returns a *new* [`Browser`](#browser) instance without any base URI applied.
     *
     * See also [`withBase()`](#withbase).
     *
     * @return self
     * @see self::withBase()
     */
    public function withoutBase()
    {
        $browser = clone $this;
        $browser->baseUri = null;

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
     *     'streaming' => false,
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
     * Adds a [`MiddlewareInterface`] to the [`Browser`](#browser).
     *
     * Notice that the [`Browser`](#browser) is an immutable object, i.e. this
     * method actually returns a *new* [`Browser`](#browser) instance with the
     * options applied.
     *
     * @param MiddlewareInterface $middleware
     *
     * @return Browser
     */
    public function withMiddleware(MiddlewareInterface $middleware)
    {
        $browser = clone $this;
        $browser->middleware[] = $middleware;

        return $browser;
    }
}
