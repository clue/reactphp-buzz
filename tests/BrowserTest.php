<?php

use Clue\React\Buzz\Browser;
use RingCentral\Psr7\Uri;

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

    public function testResolveAbsoluteReturnsSame()
    {
        $this->assertEquals('http://example.com/', $this->browser->resolve('http://example.com/'));
    }

    public function testResolveUriInstance()
    {
        $this->assertEquals('http://example.com/', $this->browser->resolve(new Uri('http://example.com/')));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testResolveRelativeWithoutBaseFails()
    {
        $this->browser->resolve('example');
    }

    public function testWithBase()
    {
        $browser = $this->browser->withBase('http://example.com/root');

        $this->assertInstanceOf('Clue\React\Buzz\Browser', $browser);
        $this->assertNotSame($this->browser, $browser);

        return $browser;
    }

    /**
     * @depends testWithBase
     * @param Browser $browser
     */
    public function testResolveRootWithBase(Browser $browser)
    {
        $this->assertEquals('http://example.com/root/test', $browser->resolve('/test'));
    }

    /**
     * @depends testWithBase
     * @param Browser $browser
     */
    public function testResolveRelativeWithBase(Browser $browser)
    {
        $this->assertEquals('http://example.com/root/test', $browser->resolve('test'));
    }

    /**
     * @depends testWithBase
     * @param Browser $browser
     * @expectedException UnexpectedValueException
     */
    public function testResolveWithOtherBaseFails(Browser $browser)
    {
        $browser->resolve('http://www.example.org/other');
    }

    /**
     * @depends testWithBase
     * @param Browser $browser
     */
    public function testResolveWithSameBaseInstance(Browser $browser)
    {
        $this->assertEquals('http://example.com/root/test', $browser->resolve(new Uri('http://example.com/root/test')));
    }

    /**
     * @depends testWithBase
     * @param Browser $browser
     * @expectedException UnexpectedValueException
     */
    public function testResolveWithOtherBaseInstanceFails(Browser $browser)
    {
        $browser->resolve(new Uri('http://example.org/other'));
    }

    /**
     * @depends testWithBase
     * @param Browser $browser
     */
    public function testResolveEmptyReturnsBase(Browser $browser)
    {
        $this->assertEquals('http://example.com/root', $browser->resolve(''));
    }

    /**
     * @depends testWithBase
     * @param Browser $browser
     */
    public function testResolveUriTemplateWithBaseLeadingSlash(Browser $browser)
    {
        $this->assertEquals('http://example.com/root/?q=test', $browser->resolve('/{?q}', array('q' => 'test')));
    }

    /**
     * @depends testWithBase
     * @param Browser $browser
     */
    public function testResolveUriTemplateWithBaseQueryStringOnly(Browser $browser)
    {
        $this->assertEquals('http://example.com/root?q=test', $browser->resolve('{?q}', array('q' => 'test')));
    }

    public function testResolveUriTemplateAbsolute()
    {
        $this->assertEquals('http://example.com/?q=test', $this->browser->resolve('http://example.com/{?q}', array('q' => 'test')));
    }

    public function testResolveUriWithBaseEndsWithoutSlash()
    {
        $browser = $this->browser->withBase('http://example.com/base');

        $this->assertEquals('http://example.com/base', $browser->resolve(''));
        $this->assertEquals('http://example.com/base/', $browser->resolve('/'));

        $this->assertEquals('http://example.com/base/test', $browser->resolve('test'));
        $this->assertEquals('http://example.com/base/test', $browser->resolve('/test'));

        $this->assertEquals('http://example.com/base?key=value', $browser->resolve('?key=value'));
        $this->assertEquals('http://example.com/base/?key=value', $browser->resolve('/?key=value'));

        $this->assertEquals('http://example.com/base/test', $browser->resolve('{+path}', array('path' => 'test')));
        $this->assertEquals('http://example.com/base/test', $browser->resolve('{+path}', array('path' => '/test')));

        $this->assertEquals('http://example.com/base', $browser->resolve('http://example.com/base'));
        $this->assertEquals('http://example.com/base/another', $browser->resolve('http://example.com/base/another'));
        $this->assertEquals('http://example.com/base?test', $browser->resolve('http://example.com/base?test'));
    }

    public function testResolveUriWithBaseEndsWithSlash()
    {
        $browser = $this->browser->withBase('http://example.com/base/');

        $this->assertEquals('http://example.com/base/', $browser->resolve(''));
        $this->assertEquals('http://example.com/base/', $browser->resolve('/'));

        $this->assertEquals('http://example.com/base/test', $browser->resolve('test'));
        $this->assertEquals('http://example.com/base/test', $browser->resolve('/test'));

        $this->assertEquals('http://example.com/base/?key=value', $browser->resolve('?key=value'));
        $this->assertEquals('http://example.com/base/?key=value', $browser->resolve('/?key=value'));

        $this->assertEquals('http://example.com/base/test', $browser->resolve('{+path}', array('path' => 'test')));
        $this->assertEquals('http://example.com/base/test', $browser->resolve('{+path}', array('path' => '/test')));

        $this->assertEquals('http://example.com/base/', $browser->resolve('http://example.com/base/'));
        $this->assertEquals('http://example.com/base/another', $browser->resolve('http://example.com/base/another'));
        $this->assertEquals('http://example.com/base/?test', $browser->resolve('http://example.com/base/?test'));
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
        $browser = $this->browser->withBase('http://example.com/base');

        $browser->resolve($other);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithBaseUriNotAbsoluteFails()
    {
        $this->browser->withBase('hello');
    }

    public function testWithBaseUriInstanceTemplateParametersWillStayEscaped()
    {
        $uri = new Uri('http://example.com/{version}/');
        $this->assertEquals('http://example.com/%7Bversion%7D/', $uri);

        $browser = $this->browser->withBase($uri);
        $this->assertEquals('http://example.com/%7Bversion%7D/', $browser->resolve('', array('version' => 1)));
    }

    public function testWithBaseUriTemplateParametersWillBeEscaped()
    {
        $browser = $this->browser->withBase('http://example.com/{version}/');

        $this->assertEquals('http://example.com/%7Bversion%7D/', $browser->resolve('', array('version' => 1)));
    }
}
