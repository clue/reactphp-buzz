<?php

use Clue\React\Buzz\Message\Uri;

class UriTest extends TestCase
{
    public function testUriSimple()
    {
        $uri = new Uri('http://www.lueck.tv/');

        $this->assertEquals('http', $uri->getScheme());
        $this->assertEquals('www.lueck.tv', $uri->getHost());
        $this->assertEquals('/', $uri->getPath());

        $this->assertEquals(null, $uri->getPort());
        $this->assertEquals('', $uri->getQuery());
    }

    public function testUriComplete()
    {
        $uri = new Uri('https://example.com:8080/?just=testing');

        $this->assertEquals('https', $uri->getScheme());
        $this->assertEquals('example.com', $uri->getHost());
        $this->assertEquals(8080, $uri->getPort());
        $this->assertEquals('/', $uri->getPath());
        $this->assertEquals('just=testing', $uri->getQuery());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidUri()
    {
        new Uri('invalid');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidPlaceholderUri()
    {
        new Uri('http://example.com/{version}');
    }

    public function testUriExpandBaseEndsWithoutSlash()
    {
        $base = new Uri('http://example.com/base');

        $this->assertEquals('http://example.com/base', $base->expandBase(''));
        $this->assertEquals('http://example.com/base/', $base->expandBase('/'));
        $this->assertEquals('http://example.com/base/test', $base->expandBase('test'));
        $this->assertEquals('http://example.com/base/test', $base->expandBase('/test'));

        $this->assertEquals('http://example.com/base?key=value', $base->expandBase('?key=value'));
        $this->assertEquals('http://example.com/base/?key=value', $base->expandBase('/?key=value'));

        $this->assertEquals('http://example.com/base', $base->expandBase('http://example.com/base'));
        $this->assertEquals('http://example.com/base/another', $base->expandBase('http://example.com/base/another'));

        return $base;
    }

    public function testUriExpandBaseEndsWithSlash()
    {
        $base = new Uri('http://example.com/base/');

        $this->assertEquals('http://example.com/base/', $base->expandBase(''));
        $this->assertEquals('http://example.com/base/', $base->expandBase('/'));
        $this->assertEquals('http://example.com/base/test', $base->expandBase('test'));
        $this->assertEquals('http://example.com/base/test', $base->expandBase('/test'));

        $this->assertEquals('http://example.com/base/?key=value', $base->expandBase('?key=value'));
        $this->assertEquals('http://example.com/base/?key=value', $base->expandBase('/?key=value'));

        $this->assertEquals('http://example.com/base/', $base->expandBase('http://example.com/base/'));
        $this->assertEquals('http://example.com/base/another', $base->expandBase('http://example.com/base/another'));
    }

    public function testAssertBase()
    {
        $base = new Uri('http://example.com/base');

        $base->assertBaseOf(new Uri('http://example.com/base'));
        $base->assertBaseOf(new Uri('http://example.com/base/'));
        $base->assertBaseOf(new Uri('http://example.com/base?test'));
    }

    public function provideOtherBaseUris()
    {
        return array(
            'other domain' => array('http://example.org/base'),
            'other scheme' => array('https://example.com/base'),
            'other port' => array('http://example.com:81/base'),
        );
    }

    /**
     * @param string $other
     * @dataProvider provideOtherBaseUris
     * @expectedException UnexpectedValueException
     */
    public function testAssertNotBase($other)
    {
        $base = new Uri('http://example.com/base');

        $base->assertBaseOf(new Uri($other));
    }

    public function testResolveRelative()
    {
        $base = new Uri('http://example.com/base/');

        $this->assertEquals('http://example.com/base/', $base->resolve(''));
        $this->assertEquals('http://example.com/', $base->resolve('/'));

        $this->assertEquals('http://example.com/base/a', $base->resolve('a'));
        $this->assertEquals('http://example.com/a', $base->resolve('../a'));
    }

    public function testResolveAbsolute()
    {
        $base = new Uri('http://example.org/');

        $this->assertEquals('http://www.example.com/', $base->resolve('http://www.example.com/'));
    }

    public function testResolveUri()
    {
        $base = new Uri('http://example.org/');

        $this->assertEquals('http://www.example.com/', $base->resolve(new Uri('http://www.example.com/')));
    }
}
