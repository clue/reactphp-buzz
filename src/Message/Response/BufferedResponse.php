<?php

namespace Clue\React\Buzz\Message\Response;

use React\HttpClient\Response as ResponseStream;

class BufferedResponse implements ResponseInterface
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

    public function getBody()
    {
        return $this->body;
    }
}
