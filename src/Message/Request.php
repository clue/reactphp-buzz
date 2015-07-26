<?php

namespace Clue\React\Buzz\Message;

class Request implements Message
{
    private $method;
    private $uri;
    private $headers;
    private $body;

    /**
     * instantiate new Request object
     *
     * @param string        $method  all uppercase HTTP method
     * @param Uri|string    $uri     full request URI
     * @param Headers|array $headers HTTP header object or array
     * @param Body|string   $body    HTTP request message body
     */
    public function __construct($method, $uri, $headers = array(), $body = '')
    {
        if (!($uri instanceof Uri)) {
            $uri = new Uri($uri);
        }
        if (!($headers instanceof Headers)) {
            $headers = new Headers($headers);
        }
        if (!($body instanceof Body)) {
            $body = new Body($body);
        }

        $this->method  = $method;
        $this->uri     = $uri;
        $this->headers = $headers;
        $this->body    = $body;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getUrl()
    {
        // TODO: rename interface
        return $this->uri;
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
