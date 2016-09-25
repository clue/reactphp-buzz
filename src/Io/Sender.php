<?php

namespace Clue\React\Buzz\Io;

use React\HttpClient\Client as HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use React\HttpClient\Request as RequestStream;
use React\HttpClient\Response as ResponseStream;
use React\Promise\Deferred;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\SocketClient\Connector;
use React\SocketClient\SecureConnector;
use RuntimeException;
use React\SocketClient\ConnectorInterface;
use React\Dns\Resolver\Resolver;
use React\Promise;
use Clue\React\Buzz\Message\MessageFactory;
use React\Stream\ReadableStreamInterface;

class Sender
{
    /**
     * create a new default sender attached to the given event loop
     *
     * @param LoopInterface $loop
     * @return self
     */
    public static function createFromLoop(LoopInterface $loop)
    {
        return self::createFromLoopDns($loop, '8.8.8.8');
    }

    /**
     * create sender attached to the given event loop and DNS resolver
     *
     * @param LoopInterface   $loop
     * @param Resolver|string $dns  DNS resolver instance or IP address
     * @return self
     */
    public static function createFromLoopDns(LoopInterface $loop, $dns)
    {
        if (!($dns instanceof Resolver)) {
            $dnsResolverFactory = new ResolverFactory();
            $dns = $dnsResolverFactory->createCached($dns, $loop);
        }

        $connector = new Connector($loop, $dns);

        return self::createFromLoopConnectors($loop, $connector);
    }

    /**
     * create sender attached to given event loop using the given connectors
     *
     * @param LoopInterface $loop
     * @param ConnectorInterface $connector            default connector to use to establish TCP/IP connections
     * @param ConnectorInterface|null $secureConnector secure connector to use to establish TLS/SSL connections (optional, composed from given default connector)
     * @return self
     */
    public static function createFromLoopConnectors(LoopInterface $loop, ConnectorInterface $connector, ConnectorInterface $secureConnector = null)
    {
        if ($secureConnector === null) {
            $secureConnector = new SecureConnector($connector, $loop);
        }

        // create HttpClient for React 0.4/0.3 (code coverage will be achieved by testing both versions)
        // @codeCoverageIgnoreStart
        $ref = new \ReflectionClass('React\HttpClient\Client');
        if ($ref->getConstructor()->getNumberOfRequiredParameters() == 2) {
            // react/http-client:0.4 removed the $loop parameter
            $http = new HttpClient($connector, $secureConnector);
        } else {
            $http = new HttpClient($loop, $connector, $secureConnector);
        }
        // @codeCoverageIgnoreEnd

        return new self($http);
    }

    /**
     * create a sender that sends *everything* through given UNIX socket path
     *
     * @param LoopInterface $loop
     * @param string        $path
     * @return self
     */
    public static function createFromLoopUnix(LoopInterface $loop, $path)
    {
        $connector = new UnixConnector($loop, $path);

        return self::createFromLoopConnectors($loop, $connector);
    }

    private $http;

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
        if ($body->getSize() !== null && $body->getSize() !== 0 && $request->hasHeader('Content-Length') !== null) {
            $request = $request->withHeader('Content-Length', $body->getSize());
        }

        if ($body instanceof ReadableStreamInterface && $body->isReadable() && !$request->hasHeader('Content-Length')) {
            $request = $request->withHeader('Transfer-Encoding', 'chunked');
        }

        $headers = array();
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        $deferred = new Deferred();

        $requestStream = $this->http->request($request->getMethod(), (string)$uri, $headers, $request->getProtocolVersion());

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
