<?php

namespace Clue\React\Buzz\Message;

use React\HttpClient\Response as ResponseStream;
use Clue\React\Buzz\Message\HeaderBag;

class Response implements Message
{
    private $response;
    private $body = '';

    public function __construct(ResponseStream $response)
    {
        $this->response = $response;
        $body     =& $this->body;
        $response->on('data', function ($data) use (&$body) {
            $body .= $data;
            // progress
        });
    }

    public function getStatusLine()
    {
        return $this->getProtocol() . ' ' . $this->getCode() . ' ' . $this->getReasonPhrase();
    }

    public function getProtocol()
    {
        return $this->response->getProtocol();
    }

    public function getVersion()
    {
        return $this->response->getVersion();
    }

    public function getCode()
    {
        return $this->response->getCode();
    }

    public function getReasonPhrase()
    {
        return $this->response->getReasonPhrase();
    }

    public function getHeaders()
    {
        return $this->response->getHeaders();
    }

    public function getHeaderBag()
    {
        return new HeaderBag($this->getHeaders());
    }

    public function getHeader($name)
    {
        return $this->getHeaderBag()->getHeaderValue($name);
    }

    public function getBody()
    {
        return $this->body;
    }
}
