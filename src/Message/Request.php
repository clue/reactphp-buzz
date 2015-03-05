<?php

namespace Clue\React\Buzz\Message;

class Request implements Message
{
    private $method;
    private $url;
    private $headers;
    private $body;

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
        return 'HTTP/1.1';
    }

    public function getRequestLine()
    {
        return $this->method . ' ' . $this->url . ' ' . $this->getHttpVersion();
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($name)
    {
        return $this->headers->getHeaderValue($name);
    }

    public function getBody()
    {
        return $this->body;
    }
}
