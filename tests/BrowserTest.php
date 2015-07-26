<?php

use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Message\Uri;

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
}
