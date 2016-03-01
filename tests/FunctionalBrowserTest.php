<?php

use React\EventLoop\Factory;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Io\Sender;
use React\Dns\Resolver\Factory as DnsFactory;
use React\SocketClient\SecureConnector;
use React\SocketClient\TcpConnector;
use React\SocketClient\DnsConnector;
use Clue\React\Buzz\Message\ResponseException;
use Clue\React\Block;

class FunctionalBrowserTest extends TestCase
{
    private $loop;
    private $browser;

    /** base url to the httpbin service  **/
    private $base = 'http://httpbin.org/';

    public function setUp()
    {
        $this->loop = Factory::create();
        $this->browser = new Browser($this->loop);
    }

    public function testSimpleRequest()
    {
        Block\await($this->browser->get($this->base . 'get'), $this->loop);
    }

    public function testRedirectRequestRelative()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=get'), $this->loop);
    }

    public function testRedirectRequestAbsolute()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=' . urlencode($this->base . 'get')), $this->loop);
    }

    public function testNotFollowingRedirectsResolvesWithRedirectResult()
    {
        $browser = $this->browser->withOptions(array('followRedirects' => false));

        Block\await($browser->get($this->base . 'redirect/3'), $this->loop);
    }

    public function testRejectingRedirectsRejects()
    {
        $browser = $this->browser->withOptions(array('maxRedirects' => 0));

        $this->setExpectedException('RuntimeException');
        Block\await($browser->get($this->base . 'redirect/3'), $this->loop);
    }

    public function testCanAccessHttps()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $this->expectPromiseResolve($this->browser->get('https://www.google.com/'));

        $this->loop->run();
    }

    public function testVerifyPeerEnabledForBadSslRejects()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        if (!class_exists('React\SocketClient\TcpConnector')) {
            $this->markTestSkipped('Test requires SocketClient:0.5');
        }

        $dnsResolverFactory = new DnsFactory();
        $resolver = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);

        $tcp = new DnsConnector(new TcpConnector($this->loop), $resolver);
        $ssl = new SecureConnector($tcp, $this->loop, array(
            'verify_peer' => true
        ));

        $sender = Sender::createFromLoopConnectors($this->loop, $tcp, $ssl);
        $browser = $this->browser->withSender($sender);

        $this->expectPromiseReject($browser->get('https://self-signed.badssl.com/'));

        $this->loop->run();
    }

    public function testVerifyPeerDisabledForBadSslResolves()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        if (!class_exists('React\SocketClient\TcpConnector')) {
            $this->markTestSkipped('Test requires SocketClient:0.5');
        }

        $dnsResolverFactory = new DnsFactory();
        $resolver = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);

        $tcp = new DnsConnector(new TcpConnector($this->loop), $resolver);
        $ssl = new SecureConnector($tcp, $this->loop, array(
            'verify_peer' => false
        ));

        $sender = Sender::createFromLoopConnectors($this->loop, $tcp, $ssl);
        $browser = $this->browser->withSender($sender);

        $this->expectPromiseResolve($browser->get('https://self-signed.badssl.com/'));

        $this->loop->run();
    }

    public function testInvalidPort()
    {
        $this->setExpectedException('RuntimeException');
        Block\await($this->browser->get('http://www.google.com:443/'), $this->loop);
    }

    public function testErrorStatusCodeRejectsWithResponseException()
    {
        try {
            Block\await($this->browser->get($this->base . 'status/404'), $this->loop);
            $this->fail();
        } catch (ResponseException $e) {
            $this->assertEquals(404, $e->getCode());

            $this->assertInstanceOf('Clue\React\Buzz\Message\Response', $e->getResponse());
            $this->assertEquals(404, $e->getResponse()->getCode());
        }
    }
}
