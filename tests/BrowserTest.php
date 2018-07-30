<?php

use Clue\React\Block;
use Clue\React\Buzz\Browser;
use Psr\Http\Message\RequestInterface;
use React\Promise\Promise;
use RingCentral\Psr7\Uri;

class BrowserTest extends TestCase
{
    private $loop;
    private $sender;
    private $browser;

    public function setUp()
    {
        $this->loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $this->sender = $this->getMockBuilder('Clue\React\Buzz\Io\Sender')->disableOriginalConstructor()->getMock();
        $this->browser = new Browser($this->loop);

        $ref = new ReflectionProperty($this->browser, 'sender');
        $ref->setAccessible(true);
        $ref->setValue($this->browser, $this->sender);
    }

    public function testWithBase()
    {
        $browser = $this->browser->withBase('http://example.com/root');

        $this->assertInstanceOf('Clue\React\Buzz\Browser', $browser);
        $this->assertNotSame($this->browser, $browser);
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

    public function testCancelGetRequestShouldCancelUnderlyingSocketConnection()
    {
        $pending = new Promise(function () { }, $this->expectCallableOnce());

        $connector = $this->getMockBuilder('React\Socket\ConnectorInterface')->getMock();
        $connector->expects($this->once())->method('connect')->with('example.com:80')->willReturn($pending);

        $this->browser = new Browser($this->loop, $connector);

        $promise = $this->browser->get('http://example.com/');
        $promise->cancel();
    }

    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects($this->once())
            ->method('__invoke');

        return $mock;
    }

    protected function createCallableMock()
    {
        return $this->getMockBuilder('stdClass')->setMethods(array('__invoke'))->getMock();
    }
}
