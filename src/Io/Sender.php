<?php

namespace Clue\React\Buzz\Io;

use Clue\React\Buzz\Message\MessageFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\HttpClient\Client as HttpClient;
use React\HttpClient\Request as RequestStream;
use React\HttpClient\Response as ResponseStream;
use React\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use React\Stream\ReadableStreamInterface;

/**
 * [Internal] Sends requests and receives responses
 *
 * The `Sender` is responsible for passing the [`RequestInterface`](#requestinterface) objects to
 * the underlying [`HttpClient`](https://github.com/reactphp/http-client) library
 * and keeps track of its transmission and converts its reponses back to [`ResponseInterface`](#responseinterface) objects.
 *
 * It also registers everything with the main [`EventLoop`](https://github.com/reactphp/event-loop#usage)
 * and the default [`Connector`](https://github.com/reactphp/socket-client) and [DNS `Resolver`](https://github.com/reactphp/dns).
 *
 * The `Sender` class mostly exists in order to abstract changes on the underlying
 * components away from this package in order to provide backwards and forwards
 * compatibility.
 *
 * @internal You SHOULD NOT rely on this API, it is subject to change without prior notice!
 * @see Browser
 */
class Sender
{
    /**
     * create a new default sender attached to the given event loop
     *
     * This method is used internally to create the "default sender".
     *
     * You may also use this method if you need custom DNS or connector
     * settings. You can use this method manually like this:
     *
     * ```php
     * $connector = new \React\Socket\Connector($loop);
     * $sender = \Clue\React\Buzz\Io\Sender::createFromLoop($loop, $connector);
     * ```
     *
     * @param LoopInterface $loop
     * @param ConnectorInterface|null $connector
     * @return self
     */
    public static function createFromLoop(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        return new self(new HttpClient($loop, $connector));
    }

    private $http;

    /**
     * [internal] Instantiate Sender
     *
     * @param HttpClient $http
     * @internal
     */
    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    /**
     *
     * @internal
     * @param RequestInterface $request
     * @param MessageFactory $messageFactory
     * @return PromiseInterface Promise<ResponseInterface, Exception>
     */
    public function send(RequestInterface $request, MessageFactory $messageFactory)
    {
        $uri = $request->getUri();

        // URIs are required to be absolute for the HttpClient to work
        if ($uri->getScheme() === '' || $uri->getHost() === '') {
            return Promise\reject(new \InvalidArgumentException('Sending request requires absolute URI with scheme and host'));
        }

        $body = $request->getBody();

        // automatically assign a Content-Length header if the body size is known
        if ($body->getSize() !== null && $body->getSize() !== 0 && !$request->hasHeader('Content-Length')) {
            $request = $request->withHeader('Content-Length', (string)$body->getSize());
        }

        if ($body instanceof ReadableStreamInterface && $body->isReadable() && !$request->hasHeader('Content-Length')) {
            $request = $request->withHeader('Transfer-Encoding', 'chunked');
        }

        $headers = array();
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $requestStream = $this->http->request($request->getMethod(), (string)$uri, $headers, $request->getProtocolVersion());

        $deferred = new Deferred(function ($_, $reject) use ($requestStream) {
            // close request stream if request is canceled
            $reject(new \RuntimeException('Request canceled'));
            $requestStream->close();
        });

        $requestStream->on('error', function($error) use ($deferred) {
            $deferred->reject($error);
        });

        $requestStream->on('response', function (ResponseStream $responseStream) use ($deferred, $messageFactory) {
            // apply response header values from response stream
            $deferred->resolve($messageFactory->response(
                $responseStream->getVersion(),
                $responseStream->getCode(),
                $responseStream->getReasonPhrase(),
                $responseStream->getHeaders(),
                $responseStream
            ));
        });

        if ($body instanceof ReadableStreamInterface) {
            if ($body->isReadable()) {
                if ($request->hasHeader('Content-Length')) {
                    // length is known => just write to request
                    $body->pipe($requestStream);
                } else {
                    // length unknown => apply chunked transfer-encoding
                    // this should be moved somewhere else obviously
                    $body->on('data', function ($data) use ($requestStream) {
                        $requestStream->write(dechex(strlen($data)) . "\r\n" . $data . "\r\n");
                    });
                    $body->on('end', function() use ($requestStream) {
                        $requestStream->end("0\r\n\r\n");
                    });
                }
            } else {
                // stream is not readable => end request without body
                $requestStream->end();
            }
        } else {
            // body is fully buffered => write as one chunk
            $requestStream->end((string)$body);
        }

        return $deferred->promise();
    }
}
