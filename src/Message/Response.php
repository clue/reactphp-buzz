<?php

namespace Clue\React\Buzz\Message;

use Clue\React\Buzz\Message\HeaderBag;

class Response implements Message
{
    private $protocol;
    private $code;
    private $reasonPhrase;
    private $headers;
    private $body;

    public function __construct($protocol, $code, $reasonPhrase, HeaderBag $headers = null, Body $body = null)
    {
        if ($headers === null) {
            $headers = new HeaderBag();
        }
        if ($body === null) {
            $body = new Body();
        }
        $this->protocol = $protocol;
        $this->code = $code;
        $this->reasonPhrase = $reasonPhrase;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getStatusLine()
    {
        return $this->getProtocol() . ' ' . $this->getCode() . ' ' . $this->getReasonPhrase();
    }

    public function getProtocol()
    {
        return 'HTTP/' . $this->protocol;
    }

    public function getHttpVersion()
    {
       return $this->protocol;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    public function getHeaders()
    {
        return $this->headers->getAll();
    }

    public function getHeaderBag()
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
