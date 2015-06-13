<?php

namespace Clue\React\Buzz\Io;

use React\HttpClient\Client as HttpClient;
use Clue\React\Buzz\Message\Request;
use Clue\React\Buzz\Message\Response;
use React\HttpClient\Request as RequestStream;
use React\HttpClient\Response as ResponseStream;
use React\Promise\Deferred;
use Clue\React\Buzz\Message\Headers;
use Clue\React\Buzz\Message\Body;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Factory as ResolverFactory;
use React\SocketClient\Connector;
use React\SocketClient\SecureConnector;
use RuntimeException;
use React\SocketClient\ConnectorInterface;

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
        $dnsResolverFactory = new ResolverFactory();
        $resolver = $dnsResolverFactory->createCached('8.8.8.8', $loop);

        $connector = new Connector($loop, $resolver);

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

    public function send(Request $request)
    {
        $body = $request->getBody();
        $headers = $request->getHeaders()->getAll();

        // automatically assign a Content-Length header if the body is not empty
        if (!$body->isEmpty() && $request->getHeader('Content-Length') === null) {
            $headers['Content-Length'] = $body->getLength();
        }

        $deferred = new Deferred();

        $requestStream = $this->http->request($request->getMethod(), $request->getUrl(), $headers);

        $requestStream->on('error', function($error) use ($deferred) {
            $deferred->reject($error);
        });

        $requestStream->on('response', function (ResponseStream $response) use ($deferred, $requestStream) {
            $bodyBuffer = '';
            $response->on('data', function ($data) use (&$bodyBuffer) {
                $bodyBuffer .= $data;
                // progress
            });

            $response->on('end', function ($error = null) use ($deferred, $response, &$bodyBuffer) {
                if ($error !== null) {
                    $deferred->reject($error);
                } else {
                    $deferred->resolve(new Response(
                        'HTTP/' . $response->getVersion(),
                        $response->getCode(),
                        $response->getReasonPhrase(),
                        new Headers($response->getHeaders()),
                        new Body($bodyBuffer)
                    ));
                }
            });

            $deferred->progress(array('responseStream' => $response, 'requestStream' => $requestStream));
        });

        $requestStream->end((string)$body);

        return $deferred->promise();
    }
}
