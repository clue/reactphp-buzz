<?php

use Clue\React\Buzz\Browser;
use RingCentral\Psr7\Uri;
use Clue\React\Block;
use Psr\Http\Message\RequestInterface;
use React\Promise\Promise;

class BrowserTest extends TestCase
{
    private $loop;
    private $sender;
    private $browser;

    public function setUp()
    {
        $this->loop = $this->getMock('React\EventLoop\LoopInterface');
        $this->sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $this->browser = new Browser($this->loop, $this->sender);
    }

    public function testWithSender()
    {
        $sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();

        $browser = $this->browser->withSender($sender);

        $this->assertNotSame($this->browser, $browser);
    }

    public function testResolveAbsoluteWithoutPlaceholdersReturnsSame()
    {
        $this->assertEquals('http://example.com/', $this->browser->resolve('http://example.com/', array()));
    }

    public function testResolveRelativeWithoutPlaceholdersReturnsSame()
    {
        $this->assertEquals('example', $this->browser->resolve('example', array()));
    }

    public function testWithBase()
    {
        $browser = $this->browser->withBase('http://example.com/root');

        $this->assertInstanceOf('Clue\React\Buzz\Browser', $browser);
        $this->assertNotSame($this->browser, $browser);
    }

    public function testResolveUriTemplateQueryStringWithLeadingSlash()
    {
        $this->assertEquals('/?q=test', $this->browser->resolve('/{?q}', array('q' => 'test')));
    }

    public function testResolveUriTemplateQueryStringOnly()
    {
        $this->assertEquals('?q=test', $this->browser->resolve('{?q}', array('q' => 'test')));
    }

    public function testResolveUriTemplateAbsolute()
    {
        $this->assertEquals('http://example.com/?q=test', $this->browser->resolve('http://example.com/{?q}', array('q' => 'test')));
    }

    public function testResolveUriTemplatePath()
    {
        $this->assertEquals('test', $this->browser->resolve('{+path}', array('path' => 'test')));
        $this->assertEquals('/test', $this->browser->resolve('{+path}', array('path' => '/test')));
    }

    public function provideOtherUris()
    {
        return array(
            'empty returns base' => array(
                'http://example.com/base',
                '',
                'http://example.com/base',
            ),
            'absolute same as base returns base' => array(
                'http://example.com/base',
                'http://example.com/base',
                'http://example.com/base',
            ),
            'absolute below base returns absolute' => array(
                'http://example.com/base',
                'http://example.com/base/another',
                'http://example.com/base/another',
            ),
            'slash returns added slash' => array(
                'http://example.com/base',
                '/',
                'http://example.com/base/',
            ),
            'slash does not add duplicate slash if base already ends with slash' => array(
                'http://example.com/base/',
                '/',
                'http://example.com/base/',
            ),
            'relative is added behind base' => array(
                'http://example.com/base/',
                'test',
                'http://example.com/base/test',
            ),
            'relative with slash is added behind base without duplicate slashes' => array(
                'http://example.com/base/',
                '/test',
                'http://example.com/base/test',
            ),
            'relative is added behind base with automatic slash inbetween' => array(
                'http://example.com/base',
                'test',
                'http://example.com/base/test',
            ),
            'relative with slash is added behind base' => array(
                'http://example.com/base',
                '/test',
                'http://example.com/base/test',
            ),
            'query string is added behind base' => array(
                'http://example.com/base',
                '?key=value',
                'http://example.com/base?key=value',
            ),
            'query string is added behind base with slash' => array(
                'http://example.com/base/',
                '?key=value',
                'http://example.com/base/?key=value',
            ),
            'query string with slash is added behind base' => array(
                'http://example.com/base',
                '/?key=value',
                'http://example.com/base/?key=value',
            ),
            'absolute with query string below base is returned as-is' => array(
                'http://example.com/base',
                'http://example.com/base?test',
                'http://example.com/base?test',
            ),
            'urlencoded special chars will stay as-is' => array(
                'http://example.com/%7Bversion%7D/',
                '',
                'http://example.com/%7Bversion%7D/'
            ),
            'special chars will be urlencoded' => array(
                'http://example.com/{version}/',
                '',
                'http://example.com/%7Bversion%7D/'
            ),
        );
    }

    /**
     * @dataProvider provideOtherUris
     * @param string $uri
     * @param string $expected
     */
    public function testResolveUriWithBaseEndsWithoutSlash($base, $uri, $expectedAbsolute)
    {
        $browser = $this->browser->withBase($base);

        $that = $this;
        $this->sender->expects($this->once())->method('send')->with($this->callback(function (RequestInterface $request) use ($expectedAbsolute, $that) {
            $that->assertEquals($expectedAbsolute, $request->getUri());
            return true;
        }))->willReturn(new Promise(function () { }));

        $browser->get($uri);
    }

    public function provideOtherBaseUris()
    {
        return array(
            'other domain' => array('http://example.org/base/'),
            'other scheme' => array('https://example.com/base/'),
            'other port' => array('http://example.com:81/base/'),
            'other path' => array('http://example.com/other/'),
            'other path due to missing slash' => array('http://example.com/other'),
        );
    }

    /**
     * @param string $other
     * @dataProvider provideOtherBaseUris
     * @expectedException UnexpectedValueException
     */
    public function testRequestingUrlsNotBelowBaseWillRejectBeforeSending($other)
    {
        $browser = $this->browser->withBase('http://example.com/base/');

        $this->sender->expects($this->never())->method('send');

        Block\await($browser->get($other), $this->loop);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithBaseUriNotAbsoluteFails()
    {
        $this->browser->withBase('hello');
    }
}
