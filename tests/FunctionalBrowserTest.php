<?php

use React\EventLoop\Factory;
use Clue\React\Buzz\Browser;
use Clue\React\Buzz\Io\Sender;
use Clue\React\Buzz\Message\ResponseException;
use Clue\React\Block;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Promise\Stream;
use React\Socket\Connector;
use React\Stream\ThroughStream;

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

    /** @group online */
    public function testSimpleRequest()
    {
        Block\await($this->browser->get($this->base . 'get'), $this->loop);
    }

    /**
     * @expectedException RuntimeException
     * @group online
     */
    public function testCancelPromiseWillRejectRequest()
    {
        $promise = $this->browser->get($this->base . 'get');
        $promise->cancel();

        Block\await($promise, $this->loop);
    }

    /** @group online */
    public function testRedirectRequestRelative()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=get'), $this->loop);
    }

    /** @group online */
    public function testRedirectRequestAbsolute()
    {
        Block\await($this->browser->get($this->base . 'redirect-to?url=' . urlencode($this->base . 'get')), $this->loop);
    }

    /** @group online */
    public function testNotFollowingRedirectsResolvesWithRedirectResult()
    {
        $browser = $this->browser->withOptions(array('followRedirects' => false));

        Block\await($browser->get($this->base . 'redirect/3'), $this->loop);
    }

    /** @group online */
    public function testRejectingRedirectsRejects()
    {
        $browser = $this->browser->withOptions(array('maxRedirects' => 0));

        $this->setExpectedException('RuntimeException');
        Block\await($browser->get($this->base . 'redirect/3'), $this->loop);
    }

    /** @group online */
    public function testCanAccessHttps()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        Block\await($this->browser->get('https://www.google.com/'), $this->loop);
    }

    /** @group online */
    public function testVerifyPeerEnabledForBadSslRejects()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $connector = new Connector($this->loop, array(
            'tls' => array(
                'verify_peer' => true
            )
        ));

        $sender = Sender::createFromLoop($this->loop, $connector);
        $browser = $this->browser->withSender($sender);

        $this->setExpectedException('RuntimeException');
        Block\await($browser->get('https://self-signed.badssl.com/'), $this->loop);
    }

    /** @group online */
    public function testVerifyPeerDisabledForBadSslResolves()
    {
        if (!function_exists('stream_socket_enable_crypto')) {
            $this->markTestSkipped('Not supported on your platform (outdated HHVM?)');
        }

        $connector = new Connector($this->loop, array(
            'tls' => array(
                'verify_peer' => false
            )
        ));

        $sender = Sender::createFromLoop($this->loop, $connector);
        $browser = $this->browser->withSender($sender);

        Block\await($browser->get('https://self-signed.badssl.com/'), $this->loop);
    }

    /** @group online */
    public function testInvalidPort()
    {
        $this->setExpectedException('RuntimeException');
        Block\await($this->browser->get('http://www.google.com:443/'), $this->loop);
    }

    /** @group online */
    public function testErrorStatusCodeRejectsWithResponseException()
    {
        try {
            Block\await($this->browser->get($this->base . 'status/404'), $this->loop);
            $this->fail();
        } catch (ResponseException $e) {
            $this->assertEquals(404, $e->getCode());

            $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $e->getResponse());
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
        }
    }

    /** @group online */
    public function testPostString()
    {
        $response = Block\await($this->browser->post($this->base . 'post', array(), 'hello world'), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
    }

    /** @group online */
    public function testPostStreamChunked()
    {
        // httpbin used to support `Transfer-Encoding: chunked` for requests,
        // but not rejects those, so let's start our own server instance
        $that = $this;
        $server = new \React\Http\Server(function (ServerRequestInterface $request) use ($that) {
            $that->assertFalse($request->hasHeader('Content-Length'));
            $that->assertNull($request->getBody()->getSize());

            return Stream\buffer($request->getBody())->then(function ($body) {
                return new Response(
                    200,
                    array(),
                    json_encode(array(
                        'data' => $body
                    ))
                );
            });
        });
        $socket = new \React\Socket\Server(0, $this->loop);
        $server->listen($socket);

        $this->base = str_replace('tcp:', 'http:', $socket->getAddress()) . '/';

        $stream = new ThroughStream();

        $this->loop->addTimer(0.001, function () use ($stream) {
            $stream->emit('data', array('hello world'));
            $stream->emit('end');
            $stream->close();
        });

        $response = Block\await($this->browser->post($this->base . 'post', array(), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);

        $socket->close();
    }

    /** @group online */
    public function testPostStreamKnownLength()
    {
        $stream = new ThroughStream();

        $this->loop->addTimer(0.001, function () use ($stream) {
            $stream->emit('data', array('hello world'));
            $stream->close();
        });

        $response = Block\await($this->browser->post($this->base . 'post', array('Content-Length' => 11), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('hello world', $data['data']);
    }

    /** @group online */
    public function testPostStreamClosed()
    {
        $stream = new ThroughStream();
        $stream->close();

        $response = Block\await($this->browser->post($this->base . 'post', array(), $stream), $this->loop);
        $data = json_decode((string)$response->getBody(), true);

        $this->assertEquals('', $data['data']);
    }
}
