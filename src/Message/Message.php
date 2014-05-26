<?php

namespace Clue\React\Buzz\Message;

interface Message
{
    public function getHeaders();

    public function getBody();
}
