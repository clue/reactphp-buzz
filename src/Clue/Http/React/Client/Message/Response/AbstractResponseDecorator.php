<?php

namespace Clue\Http\React\Client\Message\Response;

abstract class AbstractResponseDecorator implements ResponseInterface
{
    protected $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function getProtocol()
    {
        if ($this->response === null) {
            return null;
        }
        return $this->response->getProtocol();
    }

    public function getVersion()
    {
        if ($this->response === null) {
            return null;
        }
        return $this->response->getVersion();
    }

    public function getCode()
    {
        if ($this->response === null) {
            return null;
        }
        return $this->response->getCode();
    }

    public function getReasonPhrase()
    {
        if ($this->response === null) {
            return null;
        }
        return $this->response->getReasonPhrase();
    }

    public function getHeaders()
    {
        if ($this->response === null) {
            return null;
        }
        return $this->response->getHeaders();
    }
}
