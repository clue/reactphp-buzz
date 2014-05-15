<?php

namespace Clue\React\Buzz\Message\Request;

use React\HttpClient\Request as RequestStream;
use React\HttpClient\Response as ResponseStream;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use Exception;
use React\HttpClient\Client as HttpClient;
use Clue\React\Buzz\Message\Response\BufferedResponse;

class Request
{
    private $method;
    private $url;
    private $headers;


    /* @var RequestStream */
    private $requestStream = null;

    /* @var ResponseStream */
    private $responseStream = null;

    public function __construct($method, $url, $headers = array())
    {
        $this->method  = $method;
        $this->url     = $url;
        $this->headers = $headers;
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

    public function setHeader($name, $value)
    {
        $this->headers[$name] = $value;
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    public function addHeaders($headers)
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function send(HttpClient $http, $content = null)
    {
        if ($content !== null) {
            $this->setHeader('Content-Length', strlen($content));
        }

        $deferred = new Deferred();

        $requestStream = $http->request($this->method, $this->url, $this->headers);
        $requestStream->end($content);

        $requestStream->on('error', function($error) use ($deferred) {
            $deferred->reject($error);
        });

        $requestStream->on('response', function (ResponseStream $responseStream) use ($deferred) {
            $response = new BufferedResponse($responseStream);
            // progress

            $responseStream->on('end', function ($error = null) use ($deferred, $response) {
                if ($error !== null) {
                    $deferred->reject($error);
                } else {
                    $deferred->resolve($response);
                }
            });
        });

        return $deferred->promise();
    }
}
