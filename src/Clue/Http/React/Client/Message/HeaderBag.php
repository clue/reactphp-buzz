<?php

namespace Clue\Http\React\Client\Message;

class HeaderBag
{
    public static function factory($headers)
    {
        if (!($headers instanceof self)) {
            $headers = new self($headers);
        }
        return $headers;
    }

    public function getHeader()
    {

    }

    public function hasHeader()
    {

    }
}
