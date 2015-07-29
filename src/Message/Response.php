<?php

namespace Clue\React\Buzz\Message;

use Clue\React\Buzz\Message\Headers;

class Response implements Message
{
    private $protocol;
    private $code;
    private $reasonPhrase;
    private $headers;
    private $body;

    public function __construct($protocol, $code, $reasonPhrase, $headers = array(), $body = '')
    {
        if (!($headers instanceof Headers)) {
            $headers = new Headers($headers);
        }
        if (!($body instanceof Body)) {
            $body = new Body($body);
        }

        $this->protocol = $protocol;
        $this->code = $code;
        $this->reasonPhrase = $reasonPhrase;
        $this->headers = $headers;
        $this->body = $body;
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
