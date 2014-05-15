<?php

namespace Clue\React\Buzz\Message\Response;

interface ResponseInterface
{
    public function getStatusLine();

    public function getProtocol();

    public function getVersion();

    public function getCode();

    public function getReasonPhrase();

    public function getHeaders();

    // public function getBody();
}