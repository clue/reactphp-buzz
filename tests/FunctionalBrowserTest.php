<?php

use React\EventLoop\Factory;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Io\Sender;
use React\Dns\Resolver\Factory as DnsFactory;
use React\SocketClient\SecureConnector;
use React\SocketClient\TcpConnector;
use React\SocketClient\DnsConnector;

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
        $this->expectPromiseResolve($this->browser->get($this->base . 'get'));

        $this->loop->run();
    }

    public function testRedirectRequestRelative()
    {
        $this->expectPromiseResolve($this->browser->get($this->base . 'redirect-to?url=get'));

        $this->loop->run();
    }

    public function testRedirectRequestAbsolute()
    {
        $this->expectPromiseResolve($this->browser->get($this->base . 'redirect-to?url=' . urlencode($this->base . 'get')));

        $this->loop->run();
    }

    public function testNotFollowingRedirectsResolvesWithRedirectResult()
    {
        $browser = $this->browser->withOptions(array('followRedirects' => false));

        $this->expectPromiseResolve($browser->get($this->base . 'redirect/3'));

        $this->loop->run();
    }

    public function testRejectingRedirectsRejects()
    {
        $browser = $this->browser->withOptions(array('maxRedirects' => 0));

        $this->expectPromiseReject($browser->get($this->base . 'redirect/3'));

        $this->loop->run();
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
        $this->expectPromiseReject($this->browser->get('http://www.google.com:443/'));

        $this->loop->run();
    }

    public function testErrorStatusCodeRejectsWithResponseException()
    {
        $that = $this;
        $this->expectPromiseReject($this->browser->get($this->base . 'status/404'))->then(null, function ($e) use ($that) {
            $that->assertInstanceOf('Clue\Buzz\React\Message\ResponseException', $e);
            $that->assertEquals(404, $e->getCode());

            $that->assertInstanceOf('Clue\Buzz\React\Message\Response', $e->getResponse());
            $that->assertEquals(404, $e->getResponse()->getCode());
        });

        $this->loop->run();
    }
}
