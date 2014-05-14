<?php

namespace Clue\React\Buzz\Message\Response;

use React\HttpClient\Response as ResponseStream;

class BufferedResponse extends AbstractResponseDecorator
{
    private $body = '';

    public function __construct(ResponseStream $response)
    {
        parent::__construct($response);

        $body     =& $this->body;
        $response->on('data', function ($data) use (&$body) {
            $body .= $data;
            // progress
        });
    }

    public function getBody()
    {
        return $this->body;
    }
}
