<?php

namespace Clue\React\Buzz\Message;

use React\HttpClient\Request as RequestStream;
use React\HttpClient\Response as ResponseStream;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use Exception;
use React\HttpClient\Client as HttpClient;
use Clue\React\Buzz\Message\Response\BufferedResponse;

class Request implements Message
{
    private $method;
    private $url;
    private $headers;
    private $body;


    /* @var RequestStream */
    private $requestStream = null;

    /* @var ResponseStream */
    private $responseStream = null;

    public function __construct($method, $url, Headers $headers = null, Body $body = null)
    {
        if ($headers === null) {
            $headers = new Headers();
        }
        if ($body === null) {
            $body = new Body();
        }

        $this->method  = $method;
        $this->url     = $url;
        $this->headers = $headers;
        $this->body    = $body;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getHttpVersion()
    {
        return '1.1';
    }

    public function getRequestLine()
    {
        return $this->method . ' ' . $this->url . ' HTTP/' . $this->getHttpVersion();
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function send(HttpClient $http)
    {
        if (!$this->body->isEmpty()) {
            $this->setHeader('Content-Length', $this->body->getLength());
        }

        $deferred = new Deferred();

        $requestStream = $http->request($this->method, $this->url, $this->headers->getAll());
        $requestStream->end((string)$this->body);

        $requestStream->on('error', function($error) use ($deferred) {
            $deferred->reject($error);
        });

        $requestStream->on('response', function (ResponseStream $response) use ($deferred) {
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
                        $response->getVersion(),
                        $response->getCode(),
                        $response->getReasonPhrase(),
                        new Headers($response->getHeaders()),
                        new Body($bodyBuffer)
                    ));
                }
            });
        });

        return $deferred->promise();
    }
}
