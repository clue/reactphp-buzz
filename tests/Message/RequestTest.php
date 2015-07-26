<?php

use Clue\React\Buzz\Message\Request;

class RequestTest extends TestCase
{
    public function testCtor()
    {
        $request = new Request('GET', 'http://example.com/');

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://example.com/', $request->getUrl());
        $this->assertEquals('HTTP/1.1', $request->getHttpVersion());

        $this->assertEquals(array(), $request->getHeaders()->getAll());
        $this->assertTrue($request->getBody()->isEmpty());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCtorRequiresAbsoluteUri()
    {
        new Request('GET', '/test');
    }

    public function testUrl()
    {
        $request = new Request('GET', 'http://example.com/demo?just=testing');

        $this->assertEquals('http://example.com/demo?just=testing', $request->getUrl());
    }
}
