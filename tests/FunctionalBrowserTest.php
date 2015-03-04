<?php

use React\EventLoop\Factory;
use Clue\React\Buzz\Browser;

class FunctionalBrowserTest extends TestCase
{
    private $loop;
    private $browser;

    public function setUp()
    {
        $this->loop = Factory::create();
        $this->browser = new Browser($this->loop);
    }

    public function testSimpleRequest()
    {
        $this->expectPromiseResolve($this->browser->get('http://www.google.com'));

        $this->loop->run();
    }

    public function testNotFollowingRedirectsResolvesWithRedirectResult()
    {
        $browser = $this->browser->withOptions(array('followRedirects' => false));

        $this->expectPromiseResolve($browser->get('http://www.google.com'));

        $this->loop->run();
    }

    public function testRejectingRedirectsRejects()
    {
        $browser = $this->browser->withOptions(array('maxRedirects' => 0));

        $this->expectPromiseReject($browser->get('http://www.google.com'));

        $this->loop->run();
    }

    public function testInvalidPort()
    {
        $this->expectPromiseReject($this->browser->get('http://www.google.com:443'));

        $this->loop->run();
    }

    public function testInvalidPath()
    {
        $this->expectPromiseReject($this->browser->get('http://www.google.com/does-not-exist'));

        $this->loop->run();
    }
}
