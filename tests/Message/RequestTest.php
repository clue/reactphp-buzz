<?php

use Clue\React\Buzz\Message\Request;
use Clue\React\Buzz\Message\Uri;

class RequestTest extends TestCase
{
    public function testCtor()
    {
        $request = new Request('GET', 'http://example.com/');

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('http://example.com/', $request->getUri());
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

    public function testUriString()
    {
        $request = new Request('GET', 'http://example.com/demo?just=testing');

        $this->assertInstanceOf('Clue\React\Buzz\Message\Uri', $request->getUri());
        $this->assertEquals('http://example.com/demo?just=testing', $request->getUri());
    }

    public function testUriInstance()
    {
        $uri = new Uri('http://example.com/demo?just=testing');
        $request = new Request('GET', $uri);

        $this->assertSame($uri, $request->getUri());
        $this->assertEquals('http://example.com/demo?just=testing', $request->getUri());
    }
}
