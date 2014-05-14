<?php

namespace Clue\React\Buzz\Message\Request;

use React\Promise\PromisorInterface;
use React\Promise\PromiseInterface;
use React\HttpClient\Request as RequestStream;
use React\HttpClient\Response as ResponseStream;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use Exception;
use React\HttpClient\Client as HttpClient;
use Clue\React\Buzz\Message\Response\BufferedResponse;

class Request implements PromiseInterface, PromisorInterface
{
    const STATE_UNKNOWN = 0;
    const STATE_SENDING = 1;
    const STATE_RECEIVING = 2;
    const STATE_DONE = 3;
    const STATE_ERROR = 4;

    private $deferred;
    private $state = self::STATE_UNKNOWN;

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

        $this->deferred = new Deferred();
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

        $this->requestStream = $http->request($this->method, $this->url, $this->headers);
        $this->requestStream->end($content);

        $this->state = self::STATE_SENDING;
        $this->requestStream->on('error', array($this, 'onError'));
        $this->requestStream->on('response', array($this, 'onResponse'));
    }


    public function onError(Exception $exception)
    {
        $this->state = self::STATE_ERROR;
        $this->deferred->reject($exception);
    }

    public function onResponse(ResponseStream $response)
    {
        $deferred =  $this->deferred;

        $this->state = self::STATE_RECEIVING;
        $this->responseStream = $response;
        $this->response = new BufferedResponse($response);
        // progress

        $response->on('end', array($this, 'onEnd'));
    }

    public function onEnd($error = null)
    {
        if ($error !== null) {
            $this->onError($error);
        } else {
            $this->state = self::STATE_DONE;
            $this->deferred->resolve(new FulfilledPromise($this->response));
        }
    }

    public function getState()
    {
        return $this->state;
    }

    public function isPending()
    {
        return ($this->state !== self::STATE_DONE && $this->state !== self::STATE_ERROR);
    }

    public function hasResponse()
    {
        return ($this->response !== null);
    }

    public function getResponse()
    {
        if ($this->response === null) {
            throw new RuntimeException('Response not ready');
        }
        return $this->response;
    }

    public function getRequestStream()
    {
        return $this->requestStream;
    }

    public function getResponseStream()
    {
        return $this->responseStream;
    }

    public function promise()
    {
        return $this->deferred->promise();
    }

    public function then($fulfilledHandler = null, $errorHandler = null, $progressHandler = null)
    {
        return $this->deferred->then($fulfilledHandler, $errorHandler, $progressHandler);
    }
}
