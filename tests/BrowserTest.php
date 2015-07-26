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

    /**
     * @depends testWithBase
     * @param Browser $browser
     */
    public function testResolveUriTemplateWithBase(Browser $browser)
    {
        $this->assertEquals('http://example.com/root/?q=test', $browser->resolve('/{?q}', array('q' => 'test')));
    }

    public function testResolveUriTemplateAbsolute()
    {
        $this->assertEquals('http://example.com/?q=test', $this->browser->resolve('http://example.com/{?q}', array('q' => 'test')));
    }

    public function testWithBaseUriTemplateParameters()
    {
        $browser = $this->browser->withBase('http://example.com/{version}/', array('version' => 1));

        return $browser;
    }

    /**
     * @depends testWithBaseUriTemplateParameters
     * @param Browser $browser
     */
    public function testResolveUriTemplateWithDefaultParameters(Browser $browser)
    {
        $this->assertEquals('http://example.com/1/', $browser->resolve(''));
    }

    /**
     * @depends testWithBaseUriTemplateParameters
     * @param Browser $browser
     */
    public function testResolveUriTemplateOverwriteDefaultParameter(Browser $browser)
    {
        $this->assertEquals('http://example.com/2/', $browser->resolve('', array('version' => 2)));
    }

    /**
     * @depends testWithBaseUriTemplateParameters
     * @param Browser $browser
     */
    public function testResolveUriTemplateUnsetQueryParameter(Browser $browser)
    {
        $this->assertEquals('http://example.com/1/test', $browser->resolve('/test{?q}'));
    }

    /**
     * @depends testWithBaseUriTemplateParameters
     * @param Browser $browser
     */
    public function testResolveUriTemplateSetQueryParameter(Browser $browser)
    {
        $this->assertEquals('http://example.com/1/test?q=hi', $browser->resolve('/test{?q}', array('q' => 'hi')));
    }
}
