<?php

namespace Clue\React\Buzz\Message;

class Body
{
    private $message;

    public function __construct($message = '')
    {
        $this->message = (string)$message;
    }

    public function isEmpty()
    {
        return ($this->message === '');
    }

    public function getLength()
    {
        return strlen($this->message);
    }

    public function __toString()
    {
        return $this->message;
    }
}
