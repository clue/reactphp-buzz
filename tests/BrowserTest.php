<?php

use Clue\React\Buzz\Browser;
use React\Promise\Deferred;

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
}
