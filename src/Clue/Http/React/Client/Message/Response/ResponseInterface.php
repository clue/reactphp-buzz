<?php

namespace Clue\Http\React\Client\Message\Response;

interface ResponseInterface
{
    public function getProtocol();

    public function getVersion();

    public function getCode();

    public function getReasonPhrase();

    public function getHeaders();

    // public function getBody();
}