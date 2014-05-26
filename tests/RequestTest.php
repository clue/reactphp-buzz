<?php

use Clue\React\Buzz\Message\Request;

class RequestTest extends TestCase
{
    public function testCtor()
    {
        $request = new Request('GET', '/');

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/', $request->getUrl());
        $this->assertEquals('HTTP/1.1', $request->getHttpVersion());
        $this->assertEquals('GET / HTTP/1.1', $request->getRequestLine());

        $this->assertTrue($request->getBody()->isEmpty());
    }
}
