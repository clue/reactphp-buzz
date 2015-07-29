<?php

namespace Clue\React\Buzz\Message;

class Request implements Message
{
    private $method;
    private $url;
    private $headers;
    private $body;

    /**
     * instantiate new Request object
     *
     * @param string        $method  all uppercase HTTP method
     * @param string        $url     full request URL
     * @param Headers|array $headers HTTP header object or array
     * @param Body|string   $body    HTTP request message body
     */
    public function __construct($method, $url, $headers = array(), $body = '')
    {
        if (!($headers instanceof Headers)) {
            $headers = new Headers($headers);
        }
        if (!($body instanceof Body)) {
            $body = new Body($body);
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
